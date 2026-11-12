@if(config('chatify-preview.available', false))
@if(\App\Services\TeamCommunications::canAccess(auth()->user()) || (config('chatify-preview.team_enabled', false) && auth()->check()))
    @if((config('chatify-preview.enabled', false) || config('chatify-preview.team_enabled', false)) && (\App\Services\TeamCommunications::canAccess(auth()->user()) || config('chatify-preview.team_enabled', false)))
        <div class="vx-chatify-shell" :class="{'is-open': open}" x-data="vortexChatifyWidget()" x-on:keydown.escape.window="close()">
            <button type="button" class="vx-chatify-launch" x-on:click="toggle()" x-bind:aria-expanded="String(open)" aria-controls="vx-chatify-drawer" aria-label="Open messages">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8A8.5 8.5 0 0 1 8.7 3.9a8.38 8.38 0 0 1 3.8-.9L13 3a8.48 8.48 0 0 1 8 8v.5Z"/></svg>
                <span>Messages</span><span class="vx-chatify-launch-unread" x-show="totalUnread()>0" x-text="totalUnread()" aria-label="Unread messages"></span>
            </button>
            <section id="vx-chatify-drawer" class="vx-chatify-drawer vx-chatify-native" :class="{'is-full': expanded, 'has-selected': !!selected}" x-show="open" x-cloak role="dialog" :aria-modal="expanded ? 'true' : 'false'" aria-label="Team messages">
                <header class="vx-chatify-drawer-head">
                    <span class="vx-chatify-head-icon" aria-hidden="true"><x-filament::icon icon="heroicon-o-chat-bubble-left-right" /></span>
                    <div class="vx-chatify-head-copy"><strong>Messages</strong><small>Team conversations<span x-show="!connected"> · Offline</span></small></div>
                    <div class="vx-chatify-drawer-actions">
                        <button type="button" x-on:click="selected=null; messages=[]; showNew=true; loadUsers(); loadGroupRoles()" title="New message" aria-label="New message"><x-filament::icon icon="heroicon-o-pencil-square" /></button>
                        <button type="button" x-on:click="setExpanded(!expanded)" :title="expanded ? 'Exit full screen' : 'Full screen'" :aria-label="expanded ? 'Exit full screen' : 'Open messages full screen'" :aria-pressed="String(expanded)">
                            <span x-show="!expanded"><x-filament::icon icon="heroicon-o-arrows-pointing-out" /></span><span x-show="expanded" x-cloak><x-filament::icon icon="heroicon-o-arrows-pointing-in" /></span>
                        </button>
                        <a href="{{ \App\Filament\Pages\MyNotifications::getUrl() }}" class="vx-chatify-head-link" title="Message notifications (push and email)" aria-label="Message notification settings"><x-filament::icon icon="heroicon-o-bell" /></a>
                        <button type="button" x-on:click="close()" title="Close messages" aria-label="Close messages"><x-filament::icon icon="heroicon-o-x-mark" /></button>
                    </div>
                </header>
                <div class="vx-chatify-native-body" x-show="!selected || expanded">
                    <div class="vx-chatify-native-search"><input type="search" x-model="search" placeholder="Search conversations…" aria-label="Search conversations"></div><div class="vx-chatify-inbox-toolbar"><div class="vx-chatify-kinds" role="group" aria-label="Show conversations"><template x-for="k in [['all','All'],['unread','Unread'],['direct','Direct'],['group','Groups']]" :key="k[0]"><button type="button" x-on:click="kind=k[0]; unreadOnly=(k[0]==='unread')" :aria-pressed="String(kind===k[0])" :class="kind===k[0] ? 'is-active' : ''"><span x-text="k[1]"></span><b x-show="k[0]==='unread' && totalUnread()>0" x-text="totalUnread()"></b></button></template></div><button type="button" x-show="!alertsEnabled && typeof Notification !== 'undefined' && Notification.permission === 'default'" x-on:click="enableAlerts()" title="Desktop alerts while this messenger is open; device push and email are in Message notifications (bell above)">Turn on alerts</button></div>
                    <p class="vx-chatify-native-hint" x-show="loading">Loading conversations…</p>
                    <p class="vx-chatify-native-error" x-show="error" x-text="error" role="alert"></p>
                    <p class="vx-chatify-native-hint" x-show="loaded && !loading && !threads.length">No conversations yet.</p><p class="vx-chatify-native-hint" x-show="loaded && !loading && threads.length && !visibleThreads().length">No conversations match these filters.</p>
                    <template x-for="thread in visibleThreads()" :key="thread.id">
                        <button type="button" class="vx-chatify-native-thread" :class="{'is-current': String(selected?.id)===String(thread.id), 'is-unread': unread(thread)>0}" x-on:click="openThread(thread)">
                            <span class="vx-chatify-native-avatar" :class="thread.attributes?.conversation_type==='group' ? 'is-group' : ''"><span x-show="thread.attributes?.conversation_type!=='group'" x-text="initials(label(thread))"></span><span x-show="thread.attributes?.conversation_type==='group'"><x-filament::icon icon="heroicon-o-user-group" /></span><i class="vx-chatify-online" x-show="isOnline(thread)"></i></span>
                            <span class="vx-chatify-native-thread-copy"><strong x-text="label(thread)"></strong><small x-text="snippet(thread)"></small></span>
                            <span class="vx-chatify-thread-meta"><time x-text="threadTime(thread)"></time><span class="vx-chatify-unread-count" x-show="unread(thread)>0" x-text="unread(thread)" aria-label="Unread messages"></span><span class="vx-chatify-pin" x-show="isPinned(thread)" aria-label="Pinned">📌</span></span>
                        </button>
                    </template>
                </div>
                <div class="vx-chatify-empty-pane" x-show="expanded && !selected" x-cloak><span class="vx-chatify-head-icon" aria-hidden="true"><x-filament::icon icon="heroicon-o-chat-bubble-left-right" /></span><strong>Pick a conversation</strong><small>Or start a new one with the pencil above.</small></div>
                <div class="vx-chatify-conversation" x-show="selected" x-cloak>
                    <div class="vx-chatify-conversation-head">
                        <button type="button" class="vx-chatify-back" x-on:click="selected=null; messages=[]; error=''" aria-label="Back to conversations"><x-filament::icon icon="heroicon-o-arrow-left" /></button>
                        <span class="vx-chatify-native-avatar" :class="selected?.attributes?.conversation_type==='group' ? 'is-group' : ''"><span x-show="selected?.attributes?.conversation_type!=='group'" x-text="initials(selected ? label(selected) : '')"></span><span x-show="selected?.attributes?.conversation_type==='group'"><x-filament::icon icon="heroicon-o-user-group" /></span><i class="vx-chatify-online" x-show="selected && isOnline(selected)"></i></span>
                        <div class="vx-chatify-convo-title"><strong x-text="selected ? label(selected) : ''"></strong><small x-text="selected ? subtitle(selected) : ''"></small></div>
                        <div class="vx-chatify-convo-actions">
                            <button type="button" x-on:click="showMessageSearch=!showMessageSearch" :aria-pressed="String(showMessageSearch)" title="Search this conversation" aria-label="Search this conversation"><x-filament::icon icon="heroicon-o-magnifying-glass" /></button>
                            <div class="vx-chatify-more" x-data="{ more: false }" x-on:click.outside="more = false">
                                <button type="button" x-on:click="more = !more" :aria-expanded="String(more)" title="More" aria-label="More conversation actions"><x-filament::icon icon="heroicon-o-ellipsis-horizontal" /></button>
                                <div class="vx-chatify-more-menu" x-show="more" x-cloak x-on:click="more = false"><button type="button" x-on:click="togglePin()" :aria-pressed="String(selected ? isPinned(selected) : false)" :title="selected && isPinned(selected) ? 'Unpin chat' : 'Pin chat'" aria-label="Pin or unpin chat">📌</button><button type="button" x-show="selected?.attributes?.conversation_type==='group'" x-on:click="openGroupMembers()" title="Group members">Members</button><button type="button" x-show="selected?.attributes?.conversation_type==='group' && canManageGroup('edit_info')" x-on:click="renameGroup()" title="Rename group">Rename</button><button type="button" x-show="selected?.attributes?.conversation_type==='group'" x-on:click="leaveGroup()" title="Leave group">Leave</button><button type="button" x-on:click="copyConversationLink()" :title="copiedConversation ? 'Link copied' : 'Copy conversation link'" aria-label="Copy conversation link">🔗</button><button type="button" x-on:click="loadMessages()" aria-label="Refresh conversation">↻</button></div>
                            </div>
                        </div>
                    </div>
                    <div class="vx-chatify-message-search" x-show="showMessageSearch || messageSearch" x-cloak><input type="search" x-model="messageSearch" x-on:input.debounce.500ms="searchServer()" placeholder="Find in this conversation…" aria-label="Search messages in conversation"><span x-text="messageSearch ? filteredMessages().length + ' matches' : ''"></span><span x-show="historySearching">Searching history…</span></div><div class="vx-chatify-message-pane" x-ref="messagePane" aria-live="polite"><button type="button" class="vx-chatify-load-older" x-show="hasOlder && !messageSearch" x-on:click="loadOlder()" :disabled="olderLoading" x-text="olderLoading ? 'Loading…' : 'Load older messages'"></button>
                        <p class="vx-chatify-native-hint" x-show="messagesLoading && !messages.length">Loading messages…</p><p class="vx-chatify-native-hint" x-show="!messagesLoading && !messages.length">No messages yet. Start the conversation below.</p><p class="vx-chatify-native-hint" x-show="messageSearch.length>=2 && !historySearching && !filteredMessages().length">No matching messages found in the conversation.</p>
                        <template x-for="(message,index) in filteredMessages()" :key="message.id">
                            <div class="vx-chatify-message-group" :id="'vxm-'+message.id" :class="{'is-mine': isMine(message), 'is-cont': isCont(index), 'is-run-end': isRunEnd(index)}"><div class="vx-chatify-new-divider" x-show="String(message.id)===String(firstUnreadId)" x-cloak><span>New messages</span></div><div class="vx-chatify-date-separator" x-show="showDate(message,index)" x-text="formatDate(message.attributes?.created_at)"></div><div class="vx-chatify-message-row"><span class="vx-chatify-msg-avatar" x-show="!isMine(message)" x-text="initials(senderName(message))" :title="senderName(message)"></span><div class="vx-chatify-message" :class="isMine(message) ? 'is-mine' : ''"><small class="vx-chatify-sender" x-show="!isMine(message) && !isCont(index) && selected?.attributes?.conversation_type==='group'" x-text="senderName(message)"></small>
                                <div class="vx-chatify-quoted-message" x-show="message.attributes?.reply_to" x-cloak><small>Reply to <span x-text="message.attributes?.reply_to?.sender_name || 'message'"></span></small><span x-text="message.attributes?.reply_to?.body || 'Attachment'"></span></div><span class="vx-chatify-body" x-show="message.attributes?.body" x-html="linkify(message.attributes?.body)"></span>
                                <template x-for="card in cardsFor(message)" :key="card.url"><a class="vx-chatify-link-card" :href="card.url"><span class="vx-chatify-link-card-img" :class="'is-'+card.icon"><template x-if="card.image"><img :src="card.image" alt="" loading="lazy"></template><template x-if="!card.image"><span x-text="card.kind.charAt(0)"></span></template></span><span class="vx-chatify-link-card-copy"><small x-text="card.kind"></small><strong x-text="card.title"></strong><span x-text="card.detail"></span></span></a></template>
                                <template x-for="(attachment,index) in messageAttachments(message)" :key="index">
                                    <div class="vx-chatify-message-attachment">
                                        <template x-if="isImage(attachment)">
                                            <button type="button" class="vx-chatify-image-button" x-on:click="preview=attachment.url" aria-label="Enlarge image"><img :src="attachment.url" :alt="attachment.original_name || 'Shared image'" loading="lazy"></button>
                                        </template>
                                        <template x-if="!isImage(attachment)">
                                            <a :href="attachment.url" :download="attachment.original_name || null" x-text="attachment.original_name || 'Download attachment'"></a>
                                        </template>
                                    </div>
                                </template>
                                <div class="vx-chatify-message-actions"><button type="button" x-on:click="reactionPickerId = reactionPickerId===message.id ? null : message.id" :aria-expanded="reactionPickerId===message.id" aria-label="Choose a reaction" title="React">☺ React</button><template x-if="reactionPickerId===message.id"><span class="vx-chatify-reaction-picker"><template x-for="emoji in ['👍','❤️','😂','🎉','👀']" :key="emoji"><button type="button" x-on:click="toggleReaction(message,emoji);reactionPickerId=null" :title="'React '+emoji" x-text="emoji"></button></template></span></template>
                                    <button type="button" x-on:click="replyToMessage(message)" title="Reply to this message">Reply</button>
                                    <button type="button" x-show="message.attributes?.body" x-on:click="copyMessage(message)" :title="copiedId===message.id ? 'Copied' : 'Copy message text'" x-text="copiedId===message.id ? 'Copied' : 'Copy'"></button>
                                <button type="button" x-show="isMine(message) && message.attributes?.body" x-on:click="beginEdit(message)" title="Edit your message">Edit</button><button type="button" x-show="isMine(message)" x-on:click="deleteMessage(message)" title="Delete your message">Delete</button>
                                </div>
                                <div class="vx-chatify-message-reactions" x-show="reactions[message.id]?.length"><template x-for="reaction in reactionSummary(message.id)" :key="reaction.emoji"><button type="button" x-on:click="toggleReaction(message,reaction.emoji)" :aria-label="reaction.count+' reactions: '+reaction.emoji" :aria-pressed="reaction.mine" :class="reaction.mine ? 'is-mine' : ''"><span x-text="reaction.emoji"></span> <span x-text="reaction.count"></span></button></template></div><small class="vx-chatify-message-meta" x-show="isRunEnd(index) || message.attributes?.edited_at"><time :datetime="message.attributes?.created_at" :title="formatFull(message.attributes?.created_at)" x-text="formatTime(message.attributes?.created_at)"></time><span x-show="message.attributes?.edited_at"> · Edited</span><span class="vx-chatify-tick" :class="seenBy(message).length ? 'is-seen' : ''" x-show="isMine(message)" :aria-label="seenBy(message).length ? 'Seen' : 'Sent'" x-text="seenBy(message).length ? ' ✓✓' : ' ✓'"></span></small>
                            </div></div><small class="vx-chatify-seen-label" x-show="isMine(message) && index===lastMineIndex() && seenBy(message).length" x-text="seenLabel(message)"></small></div>
                        </template>
                    </div>
                    <p class="vx-chatify-native-error" x-show="error" x-text="error" role="alert"></p>
                    <div class="vx-chatify-typing" x-show="presence.typing.length" x-cloak aria-live="polite"><span class="vx-chatify-typing-dots" aria-hidden="true"><i></i><i></i><i></i></span><span x-text="typingText()"></span></div>
                    <div class="vx-chatify-reply-banner" x-show="replyTo" x-cloak><div><strong>Replying to message</strong><small x-text="replyTo?.attributes?.body || 'Attachment'"></small></div><button type="button" x-on:click="replyTo=null" aria-label="Cancel reply">✕</button></div>
                    <div class="vx-chatify-edit-banner" x-show="editingMessage" x-cloak><strong>Editing message</strong><button type="button" x-on:click="cancelEdit()">Cancel</button></div>
                    <div class="vx-chatify-attachments-selected" x-show="files.length" x-cloak>
                        <template x-for="(file,index) in files" :key="index">
                            <span :class="isImageFile(file) ? 'is-photo' : ''"><template x-if="isImageFile(file)"><img :src="fileThumb(file)" alt=""></template><span x-show="!isImageFile(file)" x-text="file.name"></span><button type="button" x-on:click="removeFile(index)" aria-label="Remove attachment">×</button></span>
                        </template>
                    </div>
                    <div class="vx-chatify-user-list" x-show="mentionQuery !== null && mentionCandidates().length" x-cloak role="listbox" aria-label="Mention a teammate" style="max-height:160px;overflow:auto">
                        <template x-for="member in mentionCandidates()" :key="member.id">
                            <button type="button" role="option" x-on:click="insertMention(member)" x-text="'@'+(member.relationships?.user?.attributes?.name || 'Teammate')"></button>
                        </template>
                    </div>
                    <form class="vx-chatify-composer" x-on:submit.prevent="send()" x-on:dragover.prevent="dragging=true" x-on:dragleave.prevent="dragging=false" x-on:drop.prevent="dragging=false;addFiles($event.dataTransfer.files)" :class="dragging ? 'is-dragging' : ''">
                        <label class="vx-chatify-attach-button" title="Attach photos or files" aria-label="Attach photos or files"><x-filament::icon icon="heroicon-o-paper-clip" /><input type="file" multiple x-ref="fileInput" x-on:change="addFiles($event.target.files);$event.target.value=''" accept="image/*,.pdf,.txt,.doc,.docx,.xls,.xlsx,.csv,.zip"></label>
                        <div class="vx-chatify-emoji" x-on:click.outside="emojiOpen=false">
                            <button type="button" class="vx-chatify-emoji-button" x-on:click="emojiOpen=!emojiOpen" :aria-expanded="String(emojiOpen)" title="Emoji" aria-label="Add an emoji"><x-filament::icon icon="heroicon-o-face-smile" /></button>
                            <div class="vx-chatify-emoji-menu" x-show="emojiOpen" x-cloak role="listbox" aria-label="Emoji"><template x-for="e in emojiSet" :key="e"><button type="button" role="option" x-on:click="insertEmoji(e)" x-text="e"></button></template></div>
                        </div>
                        <textarea x-ref="composerInput" rows="1" x-model="draft" x-on:input="updateMentionQuery();notifyTyping()" :placeholder="selected && selected.attributes?.conversation_type!=='group' ? 'Message '+label(selected).split(' ')[0]+'…' : 'Write a message…'" aria-label="Message" maxlength="10000" x-on:keydown.enter="if(!$event.shiftKey && !$event.isComposing){$event.preventDefault();mentionQuery=null;send()}" x-on:keydown.escape="mentionQuery=null"></textarea>
                        <button type="submit" :disabled="sending || (editingMessage ? !draft.trim() : (!draft.trim() && !files.length))"><x-filament::icon icon="heroicon-o-paper-airplane" /><span x-text="sending ? (editingMessage ? 'Saving…' : 'Sending…') : (editingMessage ? 'Save edit' : 'Send')"></span></button>
                    </form>
                </div>
                <div class="vx-chatify-preview" x-show="preview" x-cloak x-on:click.self="preview=null" role="dialog" aria-modal="true" aria-label="Image preview"><button type="button" x-on:click="preview=null" aria-label="Close image preview">✕</button><img :src="preview || ''" alt="Expanded chat image"></div>
                <footer class="vx-chatify-native-footer" x-show="!selected">
                    <button type="button" x-on:click="showNew=!showNew; if(showNew){loadUsers();loadGroupRoles()}">New message or group chat <span aria-hidden="true">＋</span></button>
                </footer>
                <div class="vx-chatify-new-overlay vx-chat-compose" x-show="showNew && !selected" x-cloak>
                    <div class="vx-chatify-conversation-head"><strong>New conversation</strong><button type="button" x-on:click="showNew=false" aria-label="Cancel new conversation">✕</button></div>
                    <div class="vx-chat-compose-body">
                        <div class="vx-chat-compose-mode" role="group" aria-label="Conversation type">
                            <button type="button" :class="{'is-active': !creatingGroup}" :aria-pressed="String(!creatingGroup)" x-on:click="creatingGroup=false;recipients=recipients.slice(0,1)">Direct message</button>
                            <button type="button" :class="{'is-active': creatingGroup}" :aria-pressed="String(creatingGroup)" x-on:click="creatingGroup=true">Group conversation</button>
                        </div>
                        <input type="text" x-show="creatingGroup" x-model="groupName" maxlength="120" placeholder="Group name (optional)" aria-label="Group name">
                        <label class="vx-chat-compose-label" for="vx-chat-recipient-search">Add people <span x-text="recipients.length ? recipients.length + ' selected' : 'Choose teammates'"></span></label>
                        <input id="vx-chat-recipient-search" type="search" x-model="userSearch" x-on:focus="loadUsers()" placeholder="Search by name or role…" aria-label="Search teammates by name or role" autocomplete="off">
                        <div class="vx-chat-compose-selection" x-show="recipients.length" aria-live="polite">
                            <template x-for="user in users.filter(u => recipients.includes(String(u.id)))" :key="'selected-'+user.id">
                                <button type="button" class="vx-chat-compose-chip" x-on:click="recipients=recipients.filter(id=>id!==String(user.id))" :aria-label="'Remove '+userName(user)"><span x-text="userName(user)"></span><span aria-hidden="true">×</span></button>
                            </template>
                        </div>
                        <div class="vx-chat-compose-filters" role="group" aria-label="Filter teammates"><template x-for="role in [{name:'All',value:''},{name:'Fulfillment',value:'fulfillment'},{name:'Streaming',value:'stream'},{name:'Admin',value:'admin'}]" :key="role.name"><button type="button" x-on:click="peopleRoleFilter=role.value" :class="{'is-active':peopleRoleFilter===role.value}" x-text="role.name" :aria-pressed="String(peopleRoleFilter===role.value)"></button></template></div><div class="vx-chat-compose-list-head"><strong>Teammates</strong><span x-text="users.length + ' available'"></span><button type="button" x-on:click="loadUsers()" :disabled="usersLoading" aria-label="Refresh teammates">Refresh</button></div>
                        <div class="vx-chatify-user-list vx-chat-compose-users" role="group" aria-label="Choose message recipients">
                            <p x-show="usersLoading" class="vx-chat-compose-empty" role="status">Loading teammates…</p>
                            <template x-for="user in users.filter(u=>(userName(u)+' '+(u.attributes?.role || '')).toLowerCase().includes(userSearch.trim().toLowerCase()) && (!peopleRoleFilter || String(u.attributes?.role || '').toLowerCase().includes(peopleRoleFilter)))" :key="user.id">
                                <label class="vx-chat-compose-person">
                                    <span class="vx-chat-compose-avatar" x-text="userName(user).trim().charAt(0).toUpperCase()"></span>
                                    <span class="vx-chat-compose-person-name"><strong x-text="userName(user)"></strong><small x-text="user.attributes?.role || 'Team member'"></small></span>
                                    <input type="checkbox" :value="String(user.id)" x-model="recipients" :disabled="!recipients.includes(String(user.id)) && (recipients.length >= (creatingGroup ? 49 : 1))" :aria-label="'Select '+userName(user)">
                                </label>
                            </template>
                            <p x-show="!usersLoading && !users.length" class="vx-chat-compose-empty">No teammates available. Try Refresh, or ask an administrator to check messaging access.</p>
                            <p x-show="!usersLoading && users.length && !users.some(u=>(userName(u)+' '+(u.attributes?.role || '')).toLowerCase().includes(userSearch.trim().toLowerCase()) && (!peopleRoleFilter || String(u.attributes?.role || '').toLowerCase().includes(peopleRoleFilter)))" class="vx-chat-compose-empty">No matching teammates. Try a different name.</p>
                        </div>

                        <div x-show="canCreateGroups && creatingGroup" class="vx-chat-compose-role">
                            <label for="vx-chat-role">Add by role</label>
                            <select id="vx-chat-role" x-model="selectedRole"><option value="">Choose role…</option><template x-for="role in groupRoles" :key="role.id"><option :value="String(role.id)" x-text="role.name"></option></template></select>
                            <button type="button" x-on:click="selectRoleMembers()" :disabled="!selectedRole">Add role members</button>
                        </div>

                        <p class="vx-chatify-native-error" x-show="error" x-text="error" role="alert"></p>
                    </div>
                    <div class="vx-chat-compose-footer">
                        <span x-text="creatingGroup ? recipients.length + ' of 49 selected' : recipients.length + ' of 1 selected'"></span>
                        <button type="button" class="vx-chatify-new-create" x-on:click="createConversation()" :disabled="!recipients.length || creating" x-text="creating ? 'Creating…' : (creatingGroup ? 'Create group chat' : 'Start conversation')"></button>
                    </div>
                </div>
            </section>
        </div>
        @once
            <style>
                .vx-chat-compose{display:flex!important;flex-direction:column;min-height:0;overflow:hidden}
                .vx-chat-compose[x-cloak],.vx-chat-compose[style*="display: none"]{display:none!important}
                .vx-chat-compose-body{display:flex;flex-direction:column;gap:10px;padding:16px;min-height:0;flex:1;overflow:hidden}
                .vx-chat-compose-users{scrollbar-width:thin;scrollbar-color:#b7a3d8 transparent}
                .vx-chat-compose-person:has(input:checked){background:#f1e9ff}
                .vx-chat-compose-person:focus-within{outline:2px solid #8b5cf6;outline-offset:-2px}
                .vx-chat-compose-footer .vx-chatify-new-create:disabled{opacity:.5;cursor:not-allowed}
                /* Keep recipient selection visible and the primary action anchored. */
                .vx-chatify-new-overlay.vx-chat-compose{height:100%;max-height:100%;min-height:0;background:#fff}
                .vx-chat-compose .vx-chatify-conversation-head{flex:0 0 auto;min-height:56px;padding:12px 16px;border-bottom:1px solid #eae5f1}
                .vx-chat-compose-body{overflow:hidden!important;min-height:0}
                .vx-chat-compose .vx-chat-compose-users{min-height:120px!important;flex:1 1 120px!important}
                .vx-chat-compose-footer{flex:0 0 auto;position:relative;z-index:2}
                .vx-chat-compose-person input:checked{accent-color:#6d28d9}
                .vx-chat-compose-mode{display:grid;grid-template-columns:1fr 1fr;gap:8px}
                .vx-chat-compose-mode button{padding:10px 8px;border:1px solid #ddd4ef;border-radius:10px;font-size:12px;font-weight:700;color:#493765;background:#fff}
                .vx-chat-compose-mode button.is-active{background:#f2eaff;border-color:#7c3aed;color:#6426bd}
                .vx-chat-compose-filters{display:flex;gap:5px;overflow-x:auto;padding-bottom:3px}
                .vx-chat-compose-filters button{white-space:nowrap;padding:5px 10px;border-radius:999px;border:1px solid #e2d9f0;font-size:11px;font-weight:600;color:#5d4d72}
                .vx-chat-compose-filters button.is-active{background:#6d28d9;color:#fff;border-color:#6d28d9}
                .vx-chat-compose-label{display:flex;justify-content:space-between;gap:8px;font-weight:700;font-size:13px}
                .vx-chat-compose-label span{font-weight:500;color:#776a8b}
                .vx-chat-compose-body>input[type="search"],.vx-chat-compose-body>input[type="text"]{width:100%;min-height:42px;border:1px solid #d9d0e9;border-radius:10px;padding:9px 12px;background:#fff;color:#231538}
                .vx-chat-compose-list-head{display:flex;align-items:center;gap:8px;font-size:13px}
                .vx-chat-compose-list-head span{margin-left:auto;color:#766b86;font-size:12px}
                .vx-chat-compose-list-head button{color:#7141bd;font-weight:700}
                .vx-chat-compose .vx-chat-compose-users{display:block;flex:1;min-height:150px;max-height:none;overflow-y:auto;overscroll-behavior:contain;border:1px solid #e8e2ef;border-radius:12px;background:#fff;padding:4px}
                .vx-chat-compose-person{display:flex!important;align-items:center;gap:10px;min-height:54px;padding:7px 10px;border-radius:8px;cursor:pointer}
                .vx-chat-compose-person:hover{background:#f6f1ff}
                .vx-chat-compose-avatar{display:grid;place-items:center;flex:0 0 34px;height:34px;border-radius:50%;background:#ede4ff;color:#5e2ca8;font-weight:800}
                .vx-chat-compose-person-name{display:flex;flex:1;min-width:0;flex-direction:column;gap:2px}
                .vx-chat-compose-person-name strong{font-size:13px;color:#24183a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
                .vx-chat-compose-person-name small{font-size:11px;color:#766b86}
                .vx-chat-compose-person input{width:18px;height:18px;accent-color:#7c3aed}
                .vx-chat-compose-empty{padding:16px 10px;font-size:13px;color:#776a8b;text-align:center}
                .vx-chat-compose-selection{display:flex;flex-wrap:wrap;gap:6px;max-height:80px;overflow:auto}
                .vx-chat-compose-chip{display:flex;align-items:center;gap:7px;padding:5px 9px;border-radius:999px;background:#eee5ff;color:#5b2a99;font-size:12px;font-weight:600}
                .vx-chat-compose-group{display:flex;align-items:center;gap:8px;font-size:13px}
                .vx-chat-compose-role{display:flex;flex-wrap:wrap;align-items:center;gap:8px;font-size:12px}
                .vx-chat-compose-role select{min-width:110px;max-width:170px}
                .vx-chat-compose-role button{color:#7141bd;font-weight:700}
                .vx-chat-compose-footer{display:flex;align-items:center;gap:12px;padding:12px 16px;border-top:1px solid #e9e3f1;background:#fff}
                .vx-chat-compose-footer span{white-space:nowrap;color:#766b86;font-size:12px}
                .vx-chat-compose-footer .vx-chatify-new-create{flex:1;margin:0;min-width:0}
                @media(max-width:640px){
                    .vx-chat-compose-body{padding:12px;gap:8px}
                    .vx-chat-compose-footer{padding:10px 12px}
                    .vx-chat-compose-mode button{min-height:42px}
                    .vx-chat-compose .vx-chat-compose-users{min-height:140px!important}
                    .vx-chat-compose-footer .vx-chatify-new-create{min-height:44px}
                    .vx-chat-compose .vx-chatify-conversation-head{min-height:48px}
                }
            </style>
            <script>
                function vortexChatifyWidget() {
                    return {
                        open:false, expanded:(()=>{try{return localStorage.getItem('vx-chat-expanded')==='1'}catch(e){return false}})(), kind:'all', emojiOpen:false, showMessageSearch:false, emojiSet:['👍','🙏','✅','🔥','🎉','😂','😅','👀','❤️','📦','💰','⚡'], connected:true, loaded:false, loading:false, error:'', threads:[], search:'', selected:null, messages:[],
                        messagesLoading:false, draft:'', sending:false, timer:null, lastInboxRefresh:0, chatChannel:null, onChatSignal:null, showNew:false, canCreateGroups:@json(auth()->user()->isAdmin() || auth()->user()->isOwner()), groupRoles:[], roleUsers:[], selectedRole:'', creatingGroup:false, showMembers:false, groupMembers:[], membersToAdd:[], memberSearch:'', mentionQuery:null, users:[], usersLoading:false, peopleRoleFilter:'', userSearch:'', recipients:[], groupName:'', creating:false, preview:null, files:[], dragging:false, lastMessageId:null, unreadOnly:false, messageSearch:'', pinnedIds:[], alertsEnabled:false, unreadSnapshot:null, replyTo:null, copiedId:null, copiedConversation:false, editingMessage:null, olderPage:1, hasOlder:false, olderLoading:false, historySearching:false, searchResults:[], reactions:{}, reactionPickerId:null, searchRequestId:0, messageRequestId:0, presence:{read:[],typing:[]}, presenceTimer:null, cards:{}, cardsRequested:{}, firstUnreadId:null, unreadAtOpen:0, typingSentAt:0, thumbs:new WeakMap(),
                        base:@json(url('/api/chatify/v1')), myId:@json((int) auth()->id()),
                        signalChatUpdate(conversationId) {
                            if(!conversationId)return;
                            try {this.chatChannel?.postMessage({conversationId:String(conversationId),senderId:this.myId});}catch(e){}
                        },
                        receiveChatUpdate(event) {
                            const id=String(event?.data?.conversationId || '');
                            if(!id || !this.open || !navigator.onLine)return;
                            if(String(this.selected?.id)===id)this.loadMessages();
                            this.load();
                        },
                        async api(path, options={}) {
                            const headers={'Accept':'application/json','X-Requested-With':'XMLHttpRequest',...(options.headers || {})};
                            if(options.method && options.method !== 'GET') headers['X-CSRF-TOKEN']=document.querySelector('meta[name="csrf-token"]')?.content || '';
                            const response=await fetch(this.base+path,{credentials:'same-origin',...options,headers});
                            const data=await response.json().catch(()=>({}));
                            if(!response.ok) throw new Error(data.message || 'Messaging error HTTP '+response.status);
                            return data;
                        },
                        items(data) {return Array.isArray(data?.data?.data) ? data.data.data : Array.isArray(data?.data) ? data.data : Array.isArray(data?.results) ? data.results : [];},
                        label(t) {return t?.attributes?.name || t?.relationships?.other_user?.attributes?.name || t?.relationships?.other_user?.name || t?.name || 'Conversation';},
                        setExpanded(on) {this.expanded=!!on;try{localStorage.setItem('vx-chat-expanded',on?'1':'0')}catch(e){}if(on && !this.loaded)this.load();},
                        initials(n) {const p=String(n||'?').replace(/\(.*?\)/g,' ').replace(/[^\p{L}\p{N}\s]/gu,' ').trim().split(/\s+/).filter(Boolean);return ((p[0]||'?').charAt(0)+(p.length>1?p[p.length-1].charAt(0):'')).toUpperCase();},
                        participants(t) {const p=t?.relationships?.participants;return Array.isArray(p)?p:(Array.isArray(p?.data)?p.data:[]);},
                        senderName(m) {const id=String(m?.relationships?.sender?.data?.id || m?.attributes?.user_id || '');const p=this.participants(this.selected).find(u=>String(u.id)===id);return p?.attributes?.name || (this.selected?.attributes?.conversation_type!=='group' ? this.label(this.selected) : 'Teammate');},
                        isOnline(t) {if(t?.attributes?.conversation_type==='group')return false;const o=t?.relationships?.other_user;return !!(o?.attributes?.is_online ?? o?.is_online);},
                        subtitle(t) {if(t?.attributes?.conversation_type==='group'){const n=Number(t?.attributes?.participant_count || this.participants(t).length || 0);return 'Group · '+n+' '+(n===1?'member':'members');}return this.isOnline(t) ? 'Online' : 'Direct message';},
                        threadTime(t) {const v=t?.relationships?.last_message?.attributes?.created_at || t?.attributes?.updated_at;if(!v)return '';const d=new Date(v),now=new Date();if(d.toDateString()===now.toDateString())return d.toLocaleTimeString([], {hour:'numeric',minute:'2-digit'});const y=new Date(now);y.setDate(now.getDate()-1);if(d.toDateString()===y.toDateString())return 'Yesterday';return d.toLocaleDateString([], {month:'short',day:'numeric'});},
                        insertEmoji(e) {const el=this.$refs.composerInput;const at=el?.selectionStart ?? this.draft.length;this.draft=this.draft.slice(0,at)+e+this.draft.slice(at);this.emojiOpen=false;this.$nextTick(()=>{el?.focus();try{el.setSelectionRange(at+e.length,at+e.length)}catch(_){}});},
                        snippet(t) {return t?.relationships?.last_message?.attributes?.body || 'Open conversation';},
                        userName(u) {return u?.attributes?.name || u?.name || 'Teammate';},
                        isMine(m) {return String(m?.relationships?.sender?.data?.id || m?.attributes?.user_id)===String(this.myId);},
                        messageAttachments(m) { const a=m?.attributes || {}; return Array.isArray(a.attachments) && a.attachments.length ? a.attachments.filter(x=>x?.url) : (a.attachment?.url ? [a.attachment] : []); },
                        isImage(a) { return a?.type==='image' || /\.(png|jpe?g|gif|webp|avif|bmp)(\?|$)/i.test(a?.url || '') || /\.(png|jpe?g|gif|webp|avif|bmp)$/i.test(a?.original_name || ''); },
                        init() {this.connected=navigator.onLine;if('BroadcastChannel' in window){this.chatChannel=new BroadcastChannel('vortex-chat-'+this.myId);this.onChatSignal=(event)=>this.receiveChatUpdate(event);this.chatChannel.addEventListener('message',this.onChatSignal);}window.addEventListener('online',()=>{this.connected=true;if(this.open){this.load();if(this.selected)this.loadMessages();}});window.addEventListener('offline',()=>{this.connected=false;});try {this.pinnedIds=JSON.parse(localStorage.getItem('vortex-chat-pins-'+this.myId) || '[]');if(!Array.isArray(this.pinnedIds))this.pinnedIds=[];}catch(e){this.pinnedIds=[];} this.alertsEnabled=('Notification' in window && Notification.permission==='granted');this.onVisibilityChange=()=>{if(!document.hidden && this.open && navigator.onLine){this.load();if(this.selected)this.loadMessages();}};document.addEventListener('visibilitychange',this.onVisibilityChange);const chatId=new URLSearchParams(window.location.search).get('chat');if(chatId && /^[a-zA-Z0-9_-]{1,100}$/.test(chatId)){this.open=true;this.load().then(()=>{const thread=this.threads.find(t=>String(t.id)===chatId);if(thread)this.openThread(thread);else this.error='Conversation unavailable or access denied.';});this.startPolling();}},
                        isPinned(t) {return this.pinnedIds.includes(String(t?.id));},
                        togglePin() {if(!this.selected)return;const id=String(this.selected.id);this.pinnedIds=this.isPinned(this.selected)?this.pinnedIds.filter(x=>x!==id):[...this.pinnedIds,id];try{localStorage.setItem('vortex-chat-pins-'+this.myId,JSON.stringify(this.pinnedIds));}catch(e){}},
                        visibleThreads() {const q=this.search.toLowerCase();return this.threads.filter(t=>(!this.unreadOnly || this.unread(t)>0) && (this.kind==='all' || this.kind==='unread' || (this.kind==='group' ? t?.attributes?.conversation_type==='group' : t?.attributes?.conversation_type!=='group')) && (this.label(t).toLowerCase().includes(q) || (q.length>1 && String(t?.relationships?.last_message?.attributes?.body || '').toLowerCase().includes(q)))).sort((a,b)=>(Number(this.isPinned(b))-Number(this.isPinned(a))) || (Number(this.isSelfChat(a) && !this.isPinned(a))-Number(this.isSelfChat(b) && !this.isPinned(b))));},
                        localBase:@json(url('/admin/communications')),
                        async local(path, options={}) {
                            const headers={'Accept':'application/json','X-Requested-With':'XMLHttpRequest','Content-Type':'application/json',...(options.headers || {})};
                            if(options.method && options.method !== 'GET') headers['X-CSRF-TOKEN']=document.querySelector('meta[name="csrf-token"]')?.content || '';
                            const r=await fetch(this.localBase+path,{credentials:'same-origin',...options,headers});
                            if(!r.ok) throw new Error('HTTP '+r.status);
                            return r.json();
                        },
                        async loadPresence(id) {
                            try {const data=await this.local('/conversations/'+encodeURIComponent(id)+'/presence');if(String(this.selected?.id)===String(id))this.presence={read:Array.isArray(data.read)?data.read:[],typing:Array.isArray(data.typing)?data.typing:[]};} catch(e) {}
                        },
                        notifyTyping() {
                            if(!this.selected || !this.draft.trim() || Date.now()-this.typingSentAt<3000) return;
                            this.typingSentAt=Date.now();
                            this.local('/conversations/'+encodeURIComponent(this.selected.id)+'/presence',{method:'POST',body:JSON.stringify({typing:true})}).catch(()=>{});
                        },
                        typingText() {const n=this.presence.typing;if(!n.length)return '';if(n.length===1)return n[0]+' is typing…';if(n.length===2)return n[0]+' and '+n[1]+' are typing…';return n.length+' people are typing…';},
                        // Who has read up to (or past) this message, from each participant's last read time.
                        seenBy(m) {const at=new Date(m?.attributes?.created_at || 0).getTime();return this.presence.read.filter(p=>p.last_read_at && new Date(p.last_read_at).getTime()>=at);},
                        lastMineIndex() {const list=this.filteredMessages();for(let i=list.length-1;i>=0;i--){if(this.isMine(list[i]))return i;}return -1;},
                        seenLabel(m) {const who=this.seenBy(m);if(!who.length)return '';if(this.selected?.attributes?.conversation_type!=='group')return 'Seen';const first=who.map(p=>String(p.name||'').split(' ')[0]);return 'Seen by '+(first.length<=2?first.join(' and '):first.length+' people');},
                        escapeHtml(v) {return String(v ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));},
                        // Text is escaped first; only http(s) links become anchors. Links into the app open in place, others in a new tab.
                        linkify(body) {return this.escapeHtml(body).replace(/https?:\/\/[^\s<]+[^\s<.,;:!?)\]'"]/g,u=>{const raw=u.replace(/&amp;/g,'&');let same=false;try{same=new URL(raw).origin===location.origin}catch(e){}let text=u;if(same){try{const x=new URL(raw);text=this.escapeHtml(x.pathname+x.search)}catch(e){}}return '<a href="'+u+'"'+(same?'':' target="_blank" rel="noopener noreferrer"')+'>'+text+'</a>';});},
                        appLinks(m) {const body=String(m?.attributes?.body || '');const found=body.match(/https?:\/\/[^\s<]+/g) || [];return [...new Set(found.map(u=>u.replace(/[.,;:!?)\]'"]+$/,'')))].filter(u=>{try{const x=new URL(u);return x.origin===location.origin && /^\/admin\/(shows|inventory-items|weekly-payout-batches)\/\d+/.test(x.pathname)}catch(e){return false}}).slice(0,3);},
                        cardsFor(m) {
                            const urls=this.appLinks(m);if(!urls.length)return [];
                            const missing=urls.filter(u=>!(u in this.cardsRequested));
                            if(missing.length){missing.forEach(u=>this.cardsRequested[u]=true);this.local('/link-previews',{method:'POST',body:JSON.stringify({urls:missing})}).then(d=>{this.cards={...this.cards,...(d.cards||{})};}).catch(()=>{});}
                            return urls.map(u=>this.cards[u]).filter(Boolean);
                        },
                        isImageFile(f) {return /^image\//.test(f?.type || '');},
                        fileThumb(f) {if(!this.thumbs.has(f))this.thumbs.set(f,URL.createObjectURL(f));return this.thumbs.get(f);},
                        filteredMessages() {return this.messageSearch.trim() ? this.searchResults : this.messages;},
                        totalUnread() {return this.threads.reduce((sum,t)=>sum+this.unread(t),0);},
                        async enableAlerts() {
                            if(!('Notification' in window)) {this.error='Browser notifications are unavailable here.';return;}
                            const permission=await Notification.requestPermission();
                            this.alertsEnabled=permission==='granted';
                            if(!this.alertsEnabled)this.error='Browser notification permission was not granted.';
                            this.unreadSnapshot=Object.fromEntries(this.threads.map(t=>[String(t.id),this.unread(t)]));
                        },
                        notifyNewUnread(threads) {
                            const next=Object.fromEntries(threads.map(t=>[String(t.id),this.unread(t)]));
                            if(this.unreadSnapshot && this.alertsEnabled && 'Notification' in window && Notification.permission==='granted' && document.hidden) {
                                for(const t of threads) {
                                    const count=this.unread(t),previous=this.unreadSnapshot[String(t.id)] || 0;
                                    if(count>previous && this.selected?.id!==t.id) {
                                        const notice=new Notification('New Vortex Ops message',{body:this.label(t),tag:'vortex-chat-'+t.id});
                                        notice.onclick=()=>{window.focus();this.open=true;this.openThread(t);notice.close();};
                                    }
                                }
                            }
                            this.unreadSnapshot=next;
                        },
                        unread(t) {return Number(t?.attributes?.unread_count || 0);},
                        formatDate(v) {if(!v)return '';const d=new Date(v);return Number.isNaN(d.getTime()) ? '' : new Intl.DateTimeFormat(undefined,{dateStyle:'medium'}).format(d);},
                        showDate(m,index) {return index===0 || this.formatDate(this.messages[index-1]?.attributes?.created_at)!==this.formatDate(m?.attributes?.created_at);},
                        // The day is on the separator above; each message only needs its time.
                        formatTime(v) { if(!v) return ''; const d=new Date(v); return Number.isNaN(d.getTime()) ? '' : new Intl.DateTimeFormat(undefined,{hour:'numeric',minute:'2-digit'}).format(d); },
                        formatFull(v) { if(!v) return ''; const d=new Date(v); return Number.isNaN(d.getTime()) ? '' : new Intl.DateTimeFormat(undefined,{weekday:'short',month:'short',day:'numeric',hour:'numeric',minute:'2-digit'}).format(d); },
                        senderId(m) {return String(m?.relationships?.sender?.data?.id || m?.attributes?.user_id || '');},
                        // Messages from one person within five minutes read as one run: name and avatar once, time at the end.
                        sameRun(a,b) {if(!a||!b)return false;if(this.senderId(a)!==this.senderId(b))return false;const x=new Date(a.attributes?.created_at),y=new Date(b.attributes?.created_at);return x.toDateString()===y.toDateString() && Math.abs(y-x) <= 5*60*1000;},
                        isCont(index) {const list=this.filteredMessages();return index>0 && this.sameRun(list[index-1],list[index]);},
                        isRunEnd(index) {const list=this.filteredMessages();return index===list.length-1 || !this.sameRun(list[index],list[index+1]);},
                        isSelfChat(t) {return t?.attributes?.conversation_type!=='group' && (this.label(t)==='Saved Messages' || String(t?.relationships?.other_user?.id || t?.relationships?.other_user?.attributes?.id || '')===String(this.myId));},
                        async load() {
                            if(this.loading) return;
                            this.loading=true;this.error='';
                            try {const list=this.items(await this.api('/conversations?per_page=50'));this.notifyNewUnread(list);this.threads=list;this.loaded=true;this.lastInboxRefresh=Date.now();}
                            catch(e) {this.error=e.message;} finally {this.loading=false;}
                        },
                        async openThread(t) {this.unreadAtOpen=this.unread(t);this.firstUnreadId=null;this.presence={read:[],typing:[]};this.reactionPickerId=null;this.mentionQuery=null;this.groupMembers=[];this.showMembers=false;this.selected=t;if(t?.attributes?.conversation_type==='group')this.loadMentionMembers(t.id);this.copiedConversation=false;this.messageRequestId++;this.messages=[];this.messagesLoading=false;this.olderPage=1;this.hasOlder=false;this.historySearching=false;this.replyTo=null;this.messageSearch='';this.searchResults=[];this.searchRequestId++;this.lastMessageId=null;this.error='';this.files=[];this.draft='';this.showNew=false;await this.loadMessages();},
                        pageInfo(data, count, page) {
                            const meta=data?.meta || data?.data?.meta || {};
                            const links=data?.links || data?.data?.links || {};
                            if(meta.last_page != null) return page<Number(meta.last_page);
                            if(links.next !== undefined) return Boolean(links.next);
                            return count===50;
                        },
                        mergeMessages(...batches) {
                            return [...new Map(batches.flat().map(m=>[String(m.id),m])).values()]
                                .sort((a,b)=>new Date(a.attributes?.created_at || 0)-new Date(b.attributes?.created_at || 0));
                        },
                        async loadMessages() {
                            if(!this.selected || this.messagesLoading) return;
                            const id=String(this.selected.id),requestId=++this.messageRequestId;
                            const pane=this.$refs.messagePane;
                            const nearBottom=!pane || pane.scrollHeight-pane.scrollTop-pane.clientHeight<140;
                            const firstLoad=this.messages.length===0;
                            this.messagesLoading=true;
                            try {
                                const data=await this.api('/conversations/'+encodeURIComponent(id)+'/messages?per_page=50&page=1');
                                if(String(this.selected?.id)!==id || requestId!==this.messageRequestId)return;
                                const list=this.items(data);
                                this.messages=this.mergeMessages(this.olderPage>1?this.messages:[],list);this.loadReactionBatch(list);
                                this.hasOlder=this.olderPage>1 ? this.hasOlder : this.pageInfo(data,list.length,1);
                                if(firstLoad && this.unreadAtOpen>0){const theirs=this.messages.filter(m=>!this.isMine(m));const first=theirs[Math.max(0,theirs.length-this.unreadAtOpen)];this.firstUnreadId=first ? first.id : null;}
                                this.$nextTick(()=>{
                                    const pane=this.$refs.messagePane;
                                    const mark=firstLoad && this.firstUnreadId ? document.getElementById('vxm-'+this.firstUnreadId) : null;
                                    if(pane && mark){pane.scrollTop=Math.max(0,mark.offsetTop-pane.offsetTop-24);}
                                    else if(pane && (firstLoad || nearBottom))pane.scrollTop=pane.scrollHeight;
                                    this.lastMessageId=this.messages.at(-1)?.id;
                                });
                                this.loadPresence(id);
                                this.error='';
                                if(document.visibilityState==='visible')this.api('/conversations/'+encodeURIComponent(id)+'/read',{method:'POST'}).catch(()=>{});
                            } catch(e) {
                                if(requestId===this.messageRequestId)this.error=e.message;
                            } finally {
                                if(requestId===this.messageRequestId)this.messagesLoading=false;
                            }
                        },
                        async loadOlder() {
                            if(!this.selected || !this.hasOlder || this.olderLoading || this.historySearching)return;
                            const id=String(this.selected.id),requestId=this.messageRequestId,page=this.olderPage+1;
                            const pane=this.$refs.messagePane,oldHeight=pane?.scrollHeight || 0,oldTop=pane?.scrollTop || 0;
                            this.olderLoading=true;
                            try {
                                const data=await this.api('/conversations/'+encodeURIComponent(id)+'/messages?per_page=50&page='+page);
                                if(String(this.selected?.id)!==id || requestId!==this.messageRequestId)return;
                                const list=this.items(data);
                                this.messages=this.mergeMessages(this.messages,list);this.loadReactionBatch(list);
                                this.olderPage=page;
                                this.hasOlder=this.pageInfo(data,list.length,page);
                                this.$nextTick(()=>{if(this.$refs.messagePane===pane)pane.scrollTop=oldTop+pane.scrollHeight-oldHeight;});
                            }catch(e){if(requestId===this.messageRequestId)this.error=e.message;}
                            finally{this.olderLoading=false;}
                        },
                        async searchServer() {
                            const q=this.messageSearch.trim(),id=String(this.selected?.id || ''),requestId=++this.searchRequestId;
                            this.searchResults=[];
                            if(!id || q.length<2)return;
                            this.historySearching=true;
                            try {
                                const data=await this.api('/conversations/'+encodeURIComponent(id)+'/messages/search?q='+encodeURIComponent(q)+'&per_page=50');
                                if(requestId===this.searchRequestId && String(this.selected?.id)===id)this.searchResults=this.items(data);
                            }catch(e){if(requestId===this.searchRequestId)this.error=e.message;}
                            finally{if(requestId===this.searchRequestId)this.historySearching=false;}
                        },
                        async searchHistory() {
                            if(!this.selected || this.historySearching || this.olderLoading || !this.hasOlder)return;
                            const id=String(this.selected.id),requestId=this.messageRequestId;
                            this.historySearching=true;this.error='';
                            try {
                                let page=this.olderPage;
                                for(let count=0;count<10 && this.hasOlder;count++) {
                                    page++;
                                    const data=await this.api('/conversations/'+encodeURIComponent(id)+'/messages?per_page=50&page='+page);
                                    if(String(this.selected?.id)!==id || requestId!==this.messageRequestId)return;
                                    const list=this.items(data);
                                    this.messages=this.mergeMessages(this.messages,list);
                                    this.olderPage=page;
                                    this.hasOlder=this.pageInfo(data,list.length,page);
                                }
                            }catch(e){if(requestId===this.messageRequestId)this.error=e.message;}
                            finally{this.historySearching=false;}
                        },
                        beginEdit(message) {
                            if(!this.isMine(message) || !message?.attributes?.body)return;
                            this.editingMessage=message;this.replyTo=null;this.files=[];this.draft=message.attributes.body;
                            this.$nextTick(()=>this.$refs.composerInput?.focus());
                        },
                        cancelEdit() {this.editingMessage=null;this.draft='';},
                        replyToMessage(message) {
                            this.replyTo=message;
                            this.$nextTick(()=>this.$refs.composerInput?.focus());
                        },
                        canManageGroup(permission) {
                            const membership=this.selected?.attributes?.my_membership;
                            if(!membership)return false;
                            if(membership.role==='owner' || membership.is_full_admin)return true;
                            if(membership.role==='moderator')return ['add_members','remove_members'].includes(permission);
                            return membership.role==='admin' && membership.permissions?.[permission]===true;
                        },
                        async openGroupMembers() {
                            if(!this.selected)return;
                            this.showMembers=true;this.groupMembers=[];this.membersToAdd=[];this.memberSearch='';await this.loadUsers();
                            await this.refreshGroupMembers();
                        },
                        async refreshGroupMembers() {
                            if(!this.selected)return;
                            try {this.groupMembers=this.items(await this.api('/conversations/'+encodeURIComponent(this.selected.id)+'/participants?per_page=100'));}
                            catch(e){this.error=e.message;}
                        },
                        async changeGroupRole(member) {
                            if(!this.selected || !this.canManageGroup('manage_admins'))return;
                            const role=window.prompt('Group role: member, moderator, or admin',member.attributes?.role || 'member');
                            if(role===null)return;
                            const choice=role.trim().toLowerCase();
                            if(!['member','moderator','admin'].includes(choice)){this.error='Choose member, moderator, or admin.';return;}
                            try {
                                // Scoped admin permissions avoid granting full-admin rights accidentally.
                                const body={role:choice};
                                if(choice==='admin')body.permissions={edit_info:true,add_members:true,remove_members:true,manage_admins:false};
                                await this.api('/conversations/'+encodeURIComponent(this.selected.id)+'/participants/'+encodeURIComponent(member.id),{method:'PATCH',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
                                await this.refreshGroupMembers();await this.load();
                            }catch(e){this.error=e.message;}
                        },
                        async removeGroupMember(member) {
                            if(!this.selected || !this.canManageGroup('remove_members') || !window.confirm('Remove this teammate from the group?'))return;
                            try {
                                await this.api('/conversations/'+encodeURIComponent(this.selected.id)+'/participants/'+encodeURIComponent(member.id),{method:'DELETE'});
                                await this.refreshGroupMembers();await this.load();
                            }catch(e){this.error=e.message;}
                        },
                        async addGroupMembers() {
                            if(!this.selected || !this.canManageGroup('add_members'))return;
                            const ids=[...new Set(this.membersToAdd.map(Number))];
                            if(!ids.length || ids.length>49){this.error='Select 1–49 teammates.';return;}
                            try {
                                await this.api('/conversations/'+encodeURIComponent(this.selected.id)+'/participants',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({user_ids:ids})});
                                this.membersToAdd=[];await this.refreshGroupMembers();await this.load();
                            }catch(e){this.error=e.message;}
                        },
                        async renameGroup() {
                            if(!this.selected)return;
                            const current=this.label(this.selected);
                            const name=window.prompt('Group name',current);
                            if(name===null)return;
                            const clean=name.trim();
                            if(!clean || clean.length>120){this.error='Enter a group name of 1–120 characters.';return;}
                            try {
                                const data=await this.api('/conversations/'+encodeURIComponent(this.selected.id),{method:'PATCH',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:clean})});
                                if(data?.data)this.selected=data.data;
                                await this.load();
                            }catch(e){this.error=e.message;}
                        },
                        async leaveGroup() {
                            if(!this.selected || !window.confirm('Leave this group conversation?'))return;
                            try {
                                await this.api('/conversations/'+encodeURIComponent(this.selected.id)+'/leave',{method:'POST'});
                                this.selected=null;this.showMembers=false;this.messages=[];await this.load();
                            }catch(e){this.error=e.message;}
                        },
                        async copyConversationLink() {
                            if(!this.selected)return;
                            const link=new URL(window.location.href);link.searchParams.set('chat',String(this.selected.id));
                            try {await navigator.clipboard.writeText(link.toString());this.copiedConversation=true;}
                            catch(e){this.error='Could not copy conversation link.';}
                        },
                        reactionSummary(messageId) {
                            const grouped=new Map();
                            for(const reaction of this.reactions[messageId] || []) {
                                const emoji=reaction.emoji;
                                if(!emoji)continue;
                                if(!grouped.has(emoji))grouped.set(emoji,{emoji,count:0,mine:false});
                                const group=grouped.get(emoji);
                                group.count++;
                                if(Number(reaction.user_id)===Number(this.myId))group.mine=true;
                            }
                            return [...grouped.values()];
                        },
                        async toggleReaction(message,emoji) {
                            try {
                                const url=@json(url('/admin/communications/reactions'))+'/'+encodeURIComponent(message.id);
                                const response=await fetch(url,{method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || ''},body:JSON.stringify({emoji})});
                                if(!response.ok)throw new Error('Could not update reaction');
                                await this.loadReactions(message.id);this.signalChatUpdate(message.conversation_id || this.selected?.id);
                            }catch(e){this.error=e.message;}
                        },
                        async loadReactionBatch(messages) {
                            const ids=messages.slice(0,50).map(m=>m.id).filter(Boolean);
                            if(!ids.length)return;
                            try {
                                const params=new URLSearchParams();
                                ids.forEach(id=>params.append('ids[]',id));
                                const response=await fetch(@json(url('/admin/communications/reactions/batch'))+'?'+params,{credentials:'same-origin',headers:{Accept:'application/json'}});
                                if(!response.ok)return;
                                const data=await response.json();
                                for(const id of ids)this.reactions[id]=data.reactions?.[id] || [];
                            }catch(e){}
                        },
                        async loadReactions(id) {
                            try {
                                const response=await fetch(@json(url('/admin/communications/reactions'))+'/'+encodeURIComponent(id),{credentials:'same-origin',headers:{Accept:'application/json'}});
                                if(response.ok){const data=await response.json();this.reactions[id]=data.reactions || [];}
                            }catch(e){}
                        },
                        async deleteMessage(message) {
                            if(!this.selected || !this.isMine(message) || this.sending)return;
                            if(!window.confirm('Delete this message for everyone?'))return;
                            this.sending=true;this.error='';
                            try {
                                await this.api('/messages/'+encodeURIComponent(message.id)+'?scope=all',{method:'DELETE'});
                                this.messages=this.messages.filter(m=>String(m.id)!==String(message.id));this.signalChatUpdate(this.selected.id);
                                await this.loadMessages();this.load();
                            }catch(e){this.error=e.message;}
                            finally{this.sending=false;}
                        },
                        async copyMessage(message) {
                            const body=message?.attributes?.body;
                            if(!body) return;
                            try {await navigator.clipboard.writeText(body);this.copiedId=message.id;}
                            catch(e) {this.error='Could not copy the message.';}
                        },
                        addFiles(incoming) {
                            const chosen=Array.from(incoming || []);
                            for(const file of chosen) {
                                if(file.size>20*1024*1024) {this.error='Each attachment must be 20 MB or smaller.';continue;}
                                if(this.files.length>=5) {this.error='Attach up to five files per message.';break;}
                                if(this.files.some(existing=>existing.name===file.name && existing.size===file.size && existing.lastModified===file.lastModified))continue;
                                this.files.push(file);
                            }
                        },
                        removeFile(index) {this.files.splice(index,1);},
                        async loadMentionMembers(conversationId) {
                            try {
                                const data=await this.api('/conversations/'+encodeURIComponent(conversationId)+'/participants?per_page=100');
                                if(String(this.selected?.id)===String(conversationId))this.groupMembers=this.items(data);
                            }catch(e){if(String(this.selected?.id)===String(conversationId))this.groupMembers=[];}
                        },
                        updateMentionQuery() {
                            if(this.selected?.attributes?.conversation_type!=='group'){this.mentionQuery=null;return;}
                            const before=this.draft.slice(0,this.$refs.composerInput?.selectionStart ?? this.draft.length);
                            const match=before.match(/(?:^|\s)@([\p{L}\p{N} .'-]{0,32})$/u);
                            this.mentionQuery=match ? match[1].toLowerCase() : null;
                        },
                        mentionCandidates() {
                            if(this.mentionQuery===null)return [];
                            return this.groupMembers.filter(m=>!m.attributes?.is_you && (m.relationships?.user?.attributes?.name || '').toLowerCase().includes(this.mentionQuery)).slice(0,8);
                        },
                        insertMention(member) {
                            const name=member.relationships?.user?.attributes?.name;
                            if(!name)return;
                            const input=this.$refs.composerInput;
                            const pos=input?.selectionStart ?? this.draft.length;
                            const before=this.draft.slice(0,pos);
                            const match=before.match(/(?:^|\s)@([\p{L}\p{N} .'-]{0,32})$/u);
                            if(!match)return;
                            const start=pos-match[0].length;
                            const prefix=match[0].startsWith(' ') ? ' ' : '';
                            this.draft=this.draft.slice(0,start)+prefix+'@'+name+' '+this.draft.slice(pos);
                            this.mentionQuery=null;
                            this.$nextTick(()=>input?.focus());
                        },
                        async send() {
                            if(!this.selected || (this.editingMessage ? !this.draft.trim() : (!this.draft.trim() && !this.files.length)) || this.sending) return;
                            this.sending=true;this.error='';
                            const conversationId=this.selected.id;
                            try {
                                if(this.editingMessage) {
                                    if(!this.draft.trim())throw new Error('Edited message cannot be empty.');
                                    await this.api('/messages/'+encodeURIComponent(this.editingMessage.id),{method:'PATCH',headers:{'Content-Type':'application/json'},body:JSON.stringify({body:this.draft.trim()})});
                                    this.editingMessage=null;this.draft='';this.signalChatUpdate(conversationId);
                                    await this.loadMessages();this.load();return;
                                }
                                const body=new FormData();
                                if(this.draft.trim()) body.append('body',this.draft.trim());
                                if(this.replyTo?.id) body.append('reply_to_message_id',String(this.replyTo.id));
                                this.files.forEach(file=>body.append('attachments[]',file,file.name));
                                await this.api('/conversations/'+encodeURIComponent(conversationId)+'/messages',{method:'POST',body});this.signalChatUpdate(conversationId);
                                this.draft='';this.files=[];this.replyTo=null;this.typingSentAt=0;this.local('/conversations/'+encodeURIComponent(conversationId)+'/presence',{method:'POST',body:JSON.stringify({typing:false})}).catch(()=>{});
                                await this.loadMessages();
                                this.load();
                            } catch(e) {this.error=e.message;} finally {this.sending=false;}
                        },
                        async loadGroupRoles() {
                            if(!this.canCreateGroups)return;
                            try {
                                const response=await fetch(@json(url('/admin/communications/group-roles')),{credentials:'same-origin',headers:{Accept:'application/json'}});
                                if(!response.ok)throw new Error('Unable to load roles');
                                const data=await response.json();
                                this.groupRoles=data.roles || [];this.roleUsers=data.users || [];
                            }catch(e){this.error=e.message;}
                        },
                        selectRoleMembers() {
                            const role=Number(this.selectedRole);
                            if(!role)return;
                            const matches=this.roleUsers.filter(u=>(u.role_ids || []).map(Number).includes(role)).map(u=>String(u.id));
                            const selected=[...new Set([...this.recipients,...matches])];if(selected.length>49){this.error='This role includes too many teammates for one group (maximum 49). Choose fewer people.';return;}this.recipients=selected;this.creatingGroup=true;this.error='';
                            if(this.recipients.length>1 && !this.groupName.trim())this.groupName=this.groupRoles.find(r=>Number(r.id)===role)?.name || 'Team group';
                        },
                        async loadUsers() {
                            if(this.usersLoading)return;
                            this.usersLoading=true;this.error='';
                            try {const r=await fetch(@json(url('/admin/communications/team')),{credentials:'same-origin',headers:{Accept:'application/json'}});if(!r.ok)throw new Error('Unable to load teammates');this.users=this.items(await r.json()).filter(u=>String(u.id)!==String(this.myId));}
                            catch(e) {this.error=e.message;}
                            finally {this.usersLoading=false;}
                        },
                        async createConversation() {
                            if(!this.recipients.length || this.creating) return;
                            if(this.recipients.length>49){this.error='Select no more than 49 teammates for a group.';return;}
                            this.creating=true;this.error='';
                            try {
                                const group=this.creatingGroup || this.recipients.length>1;
                                const path=group?'/conversations/group':'/conversations/direct';
                                const body=group?{name:this.groupName.trim() || 'Group chat',user_ids:this.recipients.map(Number)}:{user_id:Number(this.recipients[0])};
                                const data=await this.api(path,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
                                this.showNew=false;this.recipients=[];this.groupName='';this.creatingGroup=false;
                                await this.load();
                                const t=data?.data;
                                if(t?.id) await this.openThread(t);
                            } catch(e) {this.error=e.message;} finally {this.creating=false;}
                        },
                        startPolling() {if(this.timer)clearInterval(this.timer);this.timer=setInterval(()=>{if(!this.open || document.hidden || !navigator.onLine)return;if(this.selected)this.loadMessages();if(Date.now()-this.lastInboxRefresh>=15000)this.load();},5000);if(this.presenceTimer)clearInterval(this.presenceTimer);this.presenceTimer=setInterval(()=>{if(this.open && this.selected && !document.hidden && navigator.onLine)this.loadPresence(this.selected.id);},2500);},
                        toggle() {
                            if(this.open) {this.close();return;}
                            this.open=true;if(!this.loaded)this.load();
                            this.startPolling();
                        },
                        close() {this.open=false;this.preview=null;if(this.timer){clearInterval(this.timer);this.timer=null;}if(this.presenceTimer){clearInterval(this.presenceTimer);this.presenceTimer=null;}},
                        destroy() {if(this.chatChannel){this.chatChannel.close();this.chatChannel=null;}if(this.timer){clearInterval(this.timer);this.timer=null;}if(this.presenceTimer){clearInterval(this.presenceTimer);this.presenceTimer=null;}if(this.onVisibilityChange)document.removeEventListener('visibilitychange',this.onVisibilityChange);}
                    };
                }
            </script>
        @endonce
    @else
        <livewire:quick-messages />
    @endif
@endif

@endif
