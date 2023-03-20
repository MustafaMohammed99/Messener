import { createApp } from "vue";
import Chat from "./components/messages/Chat.vue"
import ChatList from "./components/messages/ChatList.vue"



import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

const chatApp = createApp({
    data() {
        return {
            conversations: [],
            conversation: null,
            messages: [],
            userId: userId,
            csrfToken: csrfToken,
            laravelEcho: null,
            chatChannel: null,
        }
    },
    props: [

    ],
    methods: {
        moment(time) {
            return moment(time);
        },
        scroll_messages() {
            let container = document.querySelector('#chat-body');
            container.scrollTop = container.scrollHeight;
        },
        findUser(id, conversation_id) {
            for (let i in this.conversations) {
                let conversation = this.conversations[i];
                if (conversation.id === conversation_id && conversation.participants[0].id == id) {
                    return this.conversations[i].participants[0];
                }
            }
        },
        markAsRead(id) {
            fetch(`/api/conversations/${id}/markAsRead`, {
                method: "PUT",
                mode: "cors",
                headers: {
                    "Content-Type": "application/json",
                    "accept": "application/json",
                    // 'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: JSON.stringify({
                    _token: this.$root.csrfToken
                }),
            }).then(response => response)
                .then(response => {
                    this.$root.conversation.new_messages = 0;
                })
                .catch(error => {
                    console.error('Error fetching conversations in ChatList:', error);
                });
        },
        getConversation(id_conversation) {
            for (let i in this.conversations) {
                let conversation = this.conversations[i];
                if (conversation.id == id_conversation) {
                    return conversation;
                }
            }
            return false;
        }
    },
    mounted() {
        this.laravelEcho = new Echo({
            broadcaster: 'pusher',
            key: import.meta.env.VITE_PUSHER_APP_KEY,
            cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
            wsHost: import.meta.env.VITE_PUSHER_HOST ? import.meta.env.VITE_PUSHER_HOST : `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER}.pusher.com`,
            wsPort: import.meta.env.VITE_PUSHER_PORT ?? 80,
            wssPort: import.meta.env.VITE_PUSHER_PORT ?? 443,
            forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
        });

        
        this.laravelEcho.private(`App.Models.User.${userId}`)
            .notification(function (data) {
                alert('alert')
                console.log('notification555555555555555555555555555');
                console.log(JSON.stringify(data))

            });

        this.laravelEcho
            .join(`Messenger.${this.userId}`)
            .listen('.new-message', (data) => {
                if (this.conversation && this.conversation.id == data.message.conversation_id) {
                    for (let i in this.conversations) {
                        let conversation = this.conversations[i];
                        if (conversation.id == this.$root.conversation.id) {
                            console.log(data)
                            this.$root.conversation.last_message = data.message
                            this.messages.push(data.message);
                            this.markAsRead(conversation.id)
                            this.$root.scroll_messages();
                            break;
                        }
                    }
                } else {
                    fetch(`/api/conversations/${data.message.conversation_id}`)
                        .then(response => response.json())
                        .then(json => {
                            console.log(this.conversations);
                            const index = this.conversations.findIndex(conversation => conversation.id === json.id);
                            if (index !== -1) {
                                this.conversations.splice(index, 1);
                            }
                            this.conversations.unshift(json);

                            console.log(json);
                        });


                }
            })
            .listen('.message-deleted', (data) => {
                if (this.conversation && this.conversation.id === data.id_conversation) {
                    let index = this.$root.messages.findIndex(searchMessage => searchMessage.id === data.id_message);
                    if (index !== -1) {
                        this.$root.messages.splice(index, 1);
                    }
                }
            });

        this.chatChannel = this.laravelEcho.join(`Chat`)
            .joining((user) => {
                for (let i in this.conversations) {
                    let conversation = this.conversations[i];
                    if (conversation.participants[0].id == user.id) {
                        this.conversations[i].participants[0].isOnline = true;
                        return;
                    }
                }
            })
            .leaving((user) => {
                for (let i in this.conversations) {
                    let conversation = this.conversations[i];
                    if (conversation.participants[0].id == user.id) {
                        this.conversations[i].participants[0].isOnline = false;
                        return;
                    }
                }
            })
            .listenForWhisper('typing', (e) => {
                let user = this.findUser(e.id, e.conversation_id);
                if (user) {
                    user.isTyping = true;
                }
            })
            .listenForWhisper('stopped-typing', (e) => {
                let user = this.findUser(e.id, e.conversation_id);
                if (user) {
                    user.isTyping = false;
                }
            });
    }

});
chatApp.component('Chat', Chat);
chatApp.component('ChatList', ChatList);
chatApp.mount('#chat-app');
