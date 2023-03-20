<?php

namespace App\Http\Controllers;

use App\Events\MessageCreated;
use App\Events\MessageDelated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Recipient;
use App\Models\User;
use App\Notifications\TestNotification;
use App\Notifications\TestNotificationTwo;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

class MessagesController extends Controller
{
    //


    public function index($id)
    {
        $user = Auth::user();
        // $user = User::find(1);

        $users = User::all();
        Notification::send($users, new TestNotification($user));
        // Notification::send($users, new TestNotificationTwo($user));

        $conversation = $user->conversations()->findOrFail($id);
        return $conversation->messages()->with('user')
            ->where(function ($query) use ($user) {
                $query->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id)->whereNull('deleted_at');
                })
                    ->orWhereRaw(
                        'id IN (
                    SELECT message_id FROM recipients
                    WHERE recipients.message_id = messages.id
                    AND recipients.deleted_at IS NULL
                    AND recipients.user_id = ?
                )',
                        [$user->id]
                    );
            })->withTrashed()
            ->latest()
            ->paginate();
    }


    public function store(Request $request)
    {

        $request->validate([
            'conversation_id' => 'required_without:user_id|int|exists:conversations,id',
            'user_id' => 'required_without:conversation_id|int|exists:users,id',
            'message' => 'required_without:attachment',
            'attachment' => 'required_without:message'
        ]);

        $user = Auth::user();
        // $user = User::find(1);

        $conversation_id = $request->conversation_id;
        $user_id = $request->user_id;
        DB::beginTransaction();
        try {
            if ($conversation_id) {
                $conversation = $user->conversations()->findOrFail($request->conversation_id);
            } else {
                $conversation = Conversation::where('type', 'peer')
                    ->whereHas('participants', function ($builder) use ($user, $user_id) {
                        $builder->join('participants as participants2', 'participants.conversation_id', '=', 'participants2.conversation_id')
                            ->where('participants.user_id', $user->id)
                            ->where('participants2.user_id', $user_id);
                    })->first();


                if (!$conversation) {
                    $conversation = Conversation::create(['user_id' => $user->id,]);
                    $conversation->participants()->attach([
                        $user->id => ['joined_at' => now()],
                        $user_id => ['joined_at' => now()],
                    ]);
                }
            }


            $type = 'text';
            $body_message = $request->post('message');

            if ($request->hasFile('attachment')) {
                $files = $request->file('attachment');
                // $body_message = [
                //     'file_name' => $file->getClientOriginalName(),
                //     'file_size' => $file->getSize(),
                //     'mimetype' => $file->getMimeType(),
                //     'file_path' => $file->store('attachments', [
                //         'disk' => 'uploads'
                //     ]),
                // ];
                // $body_message = [];
                foreach ($files as $file) {
                    $body_message[] = [
                        'file_name' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                        'mimetype' => $file->getMimeType(),
                        'file_path' => $file->store('attachments', [
                            'disk' => 'uploads'
                        ]),
                    ];
                }
                $type = 'attachment';
            }

            // return $body_message;

            $message = $conversation->messages()->create([
                'user_id' => $user->id,
                'type' => $type,
                'body' => $body_message,
            ]);


            DB::statement('
                  insert  into recipients (user_id , message_id)
                  select user_id , ? from participants
                  where conversation_id = ?
                  AND user_id <> ?

                ', [$message->id, $conversation->id, $user->id]);

            $conversation->update([
                'last_message_id' => $message->id,
            ]);

            DB::commit();

            $message->load('user');
            broadcast(new MessageCreated($message));
        } catch (Throwable $e) {
            DB::rollBack();
            throw ($e);
        }

        return $message;
    }


    public function destroy(Request $request, $id)
    {
        $user = Auth::user();
        $result = false;
        $message = Message::findOrFail($id);

        if ($message->user_id == $user->id) {
            if ($request->target === 'me') {
                $result = $message->delete();
            } elseif ($request->target === 'all') {
                $recipients =  $message->recipients()->get();
                $id_message = $message->id;
                $id_conversation = $message->conversation_id;
                $result = $message->forceDelete();
                broadcast(new MessageDelated($recipients, $id_message, $id_conversation));
            }
        } else {
            $result = Recipient::where([
                'user_id' => Auth::user()->id,
                'message_id' => $id,
            ])->delete();
        }

        if ($result) {
            return [
                'status' => 'succes delete message'
            ];
        }
        return [
            'status' => 'failed delete message'
        ];
    }
}
