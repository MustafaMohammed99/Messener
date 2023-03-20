<template>
    <div class="card-list">
        <!-- Card -->
        <a v-for="conversation in   this.$root.conversations" v-bind:key="conversation.id"
            v-bind:href="'#' + conversation.id" @click.prevent="setConversation(conversation)"
            class="card border-0 text-reset">
            <div class="card-body">
                <div class="row gx-5">
                    <div class="col-auto">
                        <div class="avatar" :class="{ 'avatar-online': (conversation.participants[0].isOnline ?? false) }">
                            <img v-bind:src="conversation.participants[0].avatar_url" alt="#" class="avatar-img">
                        </div>
                    </div>

                    <div class="col">
                        <div class="d-flex align-items-center mb-3">
                            <h5 class="me-auto mb-0">
                                {{ conversation ? conversation.participants[0].name : '' }}
                            </h5>
                            <span class="text-muted extra-small ms-2">
                                {{ conversation ? ($root.moment(conversation.last_message.created_at).fromNow()) : '' }}
                            </span>
                        </div>

                        <div class="d-flex align-items-center">
                            <div class="line-clamp me-auto">
                                {{ conversation.last_message.type == 'attachment' ?
                                    conversation.last_message.body.file_name : conversation.last_message.body }}
                            </div>

                            <div v-if="conversation.new_messages != 0" class="badge badge-circle bg-primary ms-5">
                                <span>{{ conversation.new_messages }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </a>
        <!-- Card -->

    </div>
</template>

<script >
export default {
    methods: {
        setConversation(conversation) {
            this.$root.conversation = conversation
            if (this.$root.conversation.new_messages != 0)
                this.$root.markAsRead(conversation.id)
        }
    },
    mounted() {
        fetch('/api/conversations')
            .then(response => response.json())
            .then(json => {
                this.$root.conversations = json.data;
            })
            .catch(error => {
                console.error('Error fetching conversations in ChatList:', error);
            });
    }
}
</script>
