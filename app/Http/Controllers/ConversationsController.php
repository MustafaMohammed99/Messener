<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Recipient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class ConversationsController extends Controller
{

    public function index()
    {
        $user = Auth::user();
        // $user = User::find(1);
        return $user->conversations()->with([
            'lastMessage',
            'participants' => function ($builder) use ($user) {
                $builder->where('id', '<>', $user->id);
            },
        ])->withCount([
            'recipients as new_messages' => function ($builder) use ($user) {
                $builder->where('recipients.user_id', '=', $user->id)
                    ->whereNull('read_at');
            }
        ])->paginate();
    }


    public function show($id)
    {
        $user = Auth::user();
        return $user->conversations()->with([
            'lastMessage',
            'participants' => function($builder) use ($user) {
                $builder->where('id', '<>', $user->id);
            },])
            ->withCount([
                'recipients as new_messages' => function($builder) use ($user) {
                    $builder->where('recipients.user_id', '=', $user->id)
                        ->whereNull('read_at');
                }
            ])
            ->findOrFail($id);
    }


    public function markAsRead($id)
    {
        $result =  Recipient::where('user_id', '=', Auth::id())
            ->whereNull('read_at')
            ->whereRaw('message_id IN (
                SELECT id FROM messages WHERE conversation_id = ?
            )', [$id])
            ->update([
                'read_at' => Carbon::now(),
            ]);
        if ($result)
            return [
                'message' => 'Messages marked as read',
            ];
        return [
            'message' => 'Messages marked failed',
        ];
    }


    public function addParticipant(Request $request, Conversation $conversation)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id'
        ]);

        $isFoundParticipant = $conversation->whereHas('participants', function ($builder) use ($request) {
            $builder->where('user_id', '=', $request->user_id);
        })->first();

        if (!$isFoundParticipant) {
            DB::beginTransaction();
            try {
                $conversation->participants()->attach(
                    $request->user_id,
                    ['joined_at' => now()]
                );
                $conversation->update([
                    'type' => 'group',
                ]);
                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                throw ($e);
            }
            return [
                'status' => 'تم الاضافة بنجاح'
            ];
        }

        return [
            'status' => 'لم تتم الاضافة بسبب وجود الشخص في المحادثة'
        ];
    }


    public function removeParticipant(Request $request, Conversation $conversation)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id'
        ]);


        $isFoundParticipant = $conversation->whereHas('participants', function ($builder) use ($request) {
            $builder->where('user_id', '=', $request->user_id);
        })->first();

        if ($isFoundParticipant) {
            $conversation->participants()->detach($request->user_id);
            return [
                'status' => 'تم الحذف بنجاح'
            ];
        }
        return [
            'status' => 'لم يتم الحذف بسبب عدم وجود الشخص في المحادثة'
        ];
    }
}
