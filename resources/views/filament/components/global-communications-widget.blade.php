@if(config('chatify-preview.available', false))
@if(\App\Services\TeamCommunications::canAccess(auth()->user()) || (config('chatify-preview.team_enabled', false) && auth()->check()))
    @if((config('chatify-preview.enabled', false) || config('chatify-preview.team_enabled', false)) && (\App\Services\TeamCommunications::canAccess(auth()->user()) || config('chatify-preview.team_enabled', false)))
        <div class="vx-chatify-shell" x-data="vortexChatifyWidget()" x-on:keydown.escape.window="close()">
            <button type="button" class="vx-chatify-launch" x-on:click="toggle()" x-bind:aria-expanded="String(open)" aria-controls="vx-chatify-drawer" aria-label="Open messages">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8A8.5 8.5 0 0 1 8.7 3.9a8.38 8.38 0 0 1 3.8-.9L13 3a8.48 8.48 0 0 1 8 8v.5Z"/></svg>
                <span>Messages</span><span class="vx-chatify-launch-unread" x-show="totalUnread()>0" x-text="totalUnread()" aria-label="Unread messages"></span>
            </button>
            <section id="vx-chatify-drawer" class="vx-chatify-drawer vx-chatify-native" x-show="open" x-cloak role="dialog" aria-modal="false" aria-label="Team messages">
                <header class="vx-chatify-drawer-head">
                    <div><strong>Messages</strong><small>Vortex Ops team chat · <span x-text="connected ? 'Online · auto-refresh' : 'Offline'" x-bind:aria-label="connected ? 'Network online; messages refresh automatically' : 'Messaging offline'"></span></small></div>
                    <div class="vx-chatify-drawer-actions">
                        <button type="button" x-on:click="selected ? loadMessages() : load()" title="Refresh" aria-label="Refresh messages"><x-filament::icon icon="heroicon-o-arrow-path" /></button>
                        <button type="button" x-on:click="close()" title="Close messages" aria-label="Close messages"><x-filament::icon icon="heroicon-o-x-mark" /></button>
                    </div>
                </header>
                <div class="vx-chatify-native-body" x-show="!selected">
                    <div class="vx-chatify-native-search"><input type="search" x-model="search" placeholder="Search conversations…" aria-label="Search conversations"></div><div class="vx-chatify-inbox-toolbar"><button type="button" x-on:click="unreadOnly=!unreadOnly" :aria-pressed="String(unreadOnly)" :class="unreadOnly ? 'is-active' : ''">Unread only</button><span x-text="totalUnread() ? totalUnread()+' unread' : 'All caught up'"></span><button type="button" x-on:click="enableAlerts()" :disabled="alertsEnabled" x-text="alertsEnabled ? 'Alerts on' : 'Enable alerts'" title="Temporary desktop alerts while this messenger is open; device push and email are managed in My Notifications"></button><a href="{{ \App\Filament\Pages\MyNotifications::getUrl() }}" class="vx-chatify-notification-settings" title="Manage message push and email notifications">Notification settings</a></div>
                    <p class="vx-chatify-native-hint" x-show="loading">Loading conversations…</p>
                    <p class="vx-chatify-native-error" x-show="error" x-text="error" role="alert"></p>
                    <p class="vx-chatify-native-hint" x-show="loaded && !loading && !threads.length">No conversations yet.</p><p class="vx-chatify-native-hint" x-show="loaded && !loading && threads.length && !visibleThreads().length">No conversations match these filters.</p>
                    <template x-for="thread in visibleThreads()" :key="thread.id">
                        <button type="button" class="vx-chatify-native-thread" x-on:click="openThread(thread)">
                            <span class="vx-chatify-native-avatar" x-text="label(thread).charAt(0).toUpperCase()"></span>
                            <span class="vx-chatify-native-thread-copy"><strong x-text="label(thread)"></strong><small x-text="snippet(thread)"></small></span><span class="vx-chatify-unread-count" x-show="unread(thread)>0" x-text="unread(thread)" aria-label="Unread messages"></span>
                            <x-filament::icon icon="heroicon-m-chevron-right" />
                        </button>
                    </template>
                </div>
                <div class="vx-chatify-conversation" x-show="selected" x-cloak>
                    <div class="vx-chatify-conversation-head">
                        <button type="button" x-on:click="selected=null; messages=[]; error=''" aria-label="Back to conversations">←</button>
                        <strong x-text="selected ? label(selected) : ''"></strong><button type="button" x-on:click="togglePin()" :aria-pressed="String(selected ? isPinned(selected) : false)" :title="selected && isPinned(selected) ? 'Unpin chat' : 'Pin chat'" aria-label="Pin or unpin chat">📌</button><button type="button" x-show="selected?.attributes?.conversation_type==='group'" x-on:click="openGroupMembers()" title="Group members">Members</button><button type="button" x-show="selected?.attributes?.conversation_type==='group' && canManageGroup('edit_info')" x-on:click="renameGroup()" title="Rename group">Rename</button><button type="button" x-show="selected?.attributes?.conversation_type==='group'" x-on:click="leaveGroup()" title="Leave group">Leave</button><button type="button" x-on:click="copyConversationLink()" :title="copiedConversation ? 'Link copied' : 'Copy conversation link'" aria-label="Copy conversation link">🔗</button><button type="button" x-on:click="loadMessages()" aria-label="Refresh conversation">↻</button>
                    </div>
                    <div class="vx-chatify-message-search"><input type="search" x-model="messageSearch" x-on:input.debounce.500ms="searchServer()" placeholder="Find in this conversation…" aria-label="Search messages in conversation"><span x-text="messageSearch ? filteredMessages().length + ' matches' : ''"></span><span x-show="historySearching">Searching history…</span></div><div class="vx-chatify-message-pane" x-ref="messagePane" aria-live="polite"><button type="button" class="vx-chatify-load-older" x-show="hasOlder && !messageSearch" x-on:click="loadOlder()" :disabled="olderLoading" x-text="olderLoading ? 'Loading…' : 'Load older messages'"></button>
                        <p class="vx-chatify-native-hint" x-show="messagesLoading && !messages.length">Loading messages…</p><p class="vx-chatify-native-hint" x-show="!messagesLoading && !messages.length">No messages yet. Start the conversation below.</p><p class="vx-chatify-native-hint" x-show="messageSearch.length>=2 && !historySearching && !filteredMessages().length">No matching messages found in the conversation.</p>
                        <template x-for="(message,index) in filteredMessages()" :key="message.id">
                            <div class="vx-chatify-message-group" :class="isMine(message) ? 'is-mine' : ''"><div class="vx-chatify-date-separator" x-show="showDate(message,index)" x-text="formatDate(message.attributes?.created_at)"></div><div class="vx-chatify-message" :class="isMine(message) ? 'is-mine' : ''">
                                <div class="vx-chatify-quoted-message" x-show="message.attributes?.reply_to" x-cloak><small>Reply to <span x-text="message.attributes?.reply_to?.sender_name || 'message'"></span></small><span x-text="message.attributes?.reply_to?.body || 'Attachment'"></span></div><span x-show="message.attributes?.body" x-text="message.attributes?.body"></span>
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
                                <div class="vx-chatify-message-reactions" x-show="reactions[message.id]?.length"><template x-for="reaction in reactionSummary(message.id)" :key="reaction.emoji"><button type="button" x-on:click="toggleReaction(message,reaction.emoji)" :aria-label="reaction.count+' reactions: '+reaction.emoji" :aria-pressed="reaction.mine" :class="reaction.mine ? 'is-mine' : ''"><span x-text="reaction.emoji"></span> <span x-text="reaction.count"></span></button></template></div><small class="vx-chatify-message-meta"><time :datetime="message.attributes?.created_at" x-text="formatTime(message.attributes?.created_at)"></time><span x-show="message.attributes?.edited_at"> · Edited</span><span x-show="isMine(message)"> · Sent</span><span x-show="!isMine(message) && message.attributes?.read === true"> · Read by you</span></small>
                            </div></div>
                        </template>
                    </div>
                    <p class="vx-chatify-native-error" x-show="error" x-text="error" role="alert"></p>
                    <div class="vx-chatify-reply-banner" x-show="replyTo" x-cloak><div><strong>Replying to message</strong><small x-text="replyTo?.attributes?.body || 'Attachment'"></small></div><button type="button" x-on:click="replyTo=null" aria-label="Cancel reply">✕</button></div>
                    <div class="vx-chatify-edit-banner" x-show="editingMessage" x-cloak><strong>Editing message</strong><button type="button" x-on:click="cancelEdit()">Cancel</button></div>
                    <div class="vx-chatify-attachments-selected" x-show="files.length" x-cloak>
                        <template x-for="(file,index) in files" :key="index">
                            <span><span x-text="file.name"></span><button type="button" x-on:click="removeFile(index)" aria-label="Remove attachment">×</button></span>
                        </template>
                    </div>
                    <div class="vx-chatify-user-list" x-show="mentionQuery !== null && mentionCandidates().length" x-cloak role="listbox" aria-label="Mention a teammate" style="max-height:160px;overflow:auto">
                        <template x-for="member in mentionCandidates()" :key="member.id">
                            <button type="button" role="option" x-on:click="insertMention(member)" x-text="'@'+(member.relationships?.user?.attributes?.name || 'Teammate')"></button>
                        </template>
                    </div>
                    <form class="vx-chatify-composer" x-on:submit.prevent="send()" x-on:dragover.prevent="dragging=true" x-on:dragleave.prevent="dragging=false" x-on:drop.prevent="dragging=false;addFiles($event.dataTransfer.files)" :class="dragging ? 'is-dragging' : ''">
                        <label class="vx-chatify-attach-button" title="Attach photos or files" aria-label="Attach photos or files">＋<input type="file" multiple x-ref="fileInput" x-on:change="addFiles($event.target.files);$event.target.value=''" accept="image/*,.pdf,.txt,.doc,.docx,.xls,.xlsx,.csv,.zip"></label>
                        <textarea x-ref="composerInput" rows="1" x-model="draft" x-on:input="updateMentionQuery()" placeholder="Write a message…" aria-label="Message" maxlength="10000" x-on:keydown.enter="if(!$event.shiftKey && !$event.isComposing){$event.preventDefault();mentionQuery=null;send()}" x-on:keydown.escape="mentionQuery=null"></textarea>
                        <button type="submit" :disabled="sending || (editingMessage ? !draft.trim() : (!draft.trim() && !files.length))" x-text="sending ? (editingMessage ? 'Saving…' : 'Sending…') : (editingMessage ? 'Save edit' : 'Send')"></button>
                    </form>
                </div>
                <div class="vx-chatify-preview" x-show="preview" x-cloak x-on:click.self="preview=null" role="dialog" aria-modal="true" aria-label="Image preview"><button type="button" x-on:click="preview=null" aria-label="Close image preview">✕</button><img :src="preview || ''" alt="Expanded chat image"></div>
                <footer class="vx-chatify-native-footer" x-show="!selected">
                    <button type="button" x-on:click="showNew=!showNew; if(showNew){loadUsers();loadGroupRoles()}">New message or group chat <span aria-hidden="true">＋</span></button>
                </footer>
                <div class="vx-chatify-new-overlay" x-show="showMembers && selected" x-cloak>
                    <div class="vx-chatify-conversation-head"><strong>Group members</strong><button type="button" x-on:click="showMembers=false" aria-label="Close group members">✕</button></div>
                    <p class="vx-chatify-native-error" x-show="error" x-text="error" role="alert"></p>
                    <div class="vx-chatify-user-list">
                        <template x-for="member in groupMembers" :key="member.id">
                            <div class="vx-chatify-native-thread">
                                <span class="vx-chatify-native-thread-copy"><strong x-text="member.relationships?.user?.attributes?.name || 'Member'"></strong><small x-text="member.attributes?.role || 'member'"></small></span>
                                <button type="button" x-show="canManageGroup('manage_admins') && !member.attributes?.is_you && member.attributes?.role!=='owner'" x-on:click="changeGroupRole(member)" title="Change group permissions">Role</button><button type="button" x-show="canManageGroup('remove_members') && !member.attributes?.is_you && member.attributes?.role!=='owner'" x-on:click="removeGroupMember(member)">Remove</button>
                            </div>
                        </template>
                    </div>
                    <div x-show="canManageGroup('add_members')">
                        <input type="search" x-model="memberSearch" x-on:focus="loadUsers()" placeholder="Find teammates to add…" aria-label="Search teammates to add">
                        <div class="vx-chatify-user-list">
                            <template x-for="user in users.filter(u=>userName(u).toLowerCase().includes(memberSearch.toLowerCase()) && !groupMembers.some(m=>String(m.id)===String(user.id))).slice(0,30)" :key="user.id">
                                <label><input type="checkbox" :value="String(user.id)" x-model="membersToAdd"><span x-text="userName(user)"></span></label>
                            </template>
                        </div>
                        <button type="button" x-on:click="addGroupMembers()" :disabled="!membersToAdd.length">Add selected teammates</button>
                    </div>
                </div>
                <div class="vx-chatify-new-overlay" x-show="showNew && !selected" x-cloak>
                    <div class="vx-chatify-conversation-head"><strong>New conversation</strong><button type="button" x-on:click="showNew=false" aria-label="Cancel">✕</button></div>
                    <label class="vx-chatify-native-hint"><input type="checkbox" x-model="creatingGroup"> Create a group chat</label><input type="search" x-model="userSearch" placeholder="Find a teammate…" aria-label="Find a teammate">
                    <div class="vx-chatify-user-list">
                        <template x-for="user in users.filter(u => userName(u).toLowerCase().includes(userSearch.toLowerCase()))" :key="user.id">
                            <label><input type="checkbox" :value="String(user.id)" x-model="recipients"><span x-text="userName(user)"></span></label>
                        </template>
                    </div>
                    <div x-show="canCreateGroups" class="vx-chatify-inbox-toolbar">
                        <label>Group by role <select x-model="selectedRole"><option value="">Choose role…</option><template x-for="role in groupRoles" :key="role.id"><option :value="String(role.id)" x-text="role.name"></option></template></select></label>
                        <button type="button" x-on:click="selectRoleMembers()" :disabled="!selectedRole">Select role members</button><span x-show="selectedRole" x-text="roleUsers.filter(u=>(u.role_ids || []).map(Number).includes(Number(selectedRole))).length + ' teammates in role'"></span>
                    </div>
                    <p class="vx-chatify-native-hint" x-text="recipients.length + ' teammate(s) selected · maximum 49'"></p><input type="text" x-show="creatingGroup || recipients.length > 1" x-model="groupName" maxlength="120" placeholder="Group name" aria-label="Group name">
                    <p class="vx-chatify-native-error" x-show="error" x-text="error"></p>
                    <button type="button" class="vx-chatify-new-create" x-on:click="createConversation()" :disabled="!recipients.length || creating" x-text="creating ? 'Creating…' : 'Start conversation'"></button>
                </div>
            </section>
        </div>
        @once
            <script>
                function vortexChatifyWidget() {
                    return {
                        open:false, connected:true, loaded:false, loading:false, error:'', threads:[], search:'', selected:null, messages:[],
                        messagesLoading:false, draft:'', sending:false, timer:null, lastInboxRefresh:0, chatChannel:null, onChatSignal:null, showNew:false, canCreateGroups:@json(auth()->user()->isAdmin() || auth()->user()->isOwner()), groupRoles:[], roleUsers:[], selectedRole:'', creatingGroup:false, showMembers:false, groupMembers:[], membersToAdd:[], memberSearch:'', mentionQuery:null, users:[], userSearch:'', recipients:[], groupName:'', creating:false, preview:null, files:[], dragging:false, lastMessageId:null, unreadOnly:false, messageSearch:'', pinnedIds:[], alertsEnabled:false, unreadSnapshot:null, replyTo:null, copiedId:null, copiedConversation:false, editingMessage:null, olderPage:1, hasOlder:false, olderLoading:false, historySearching:false, searchResults:[], reactions:{}, reactionPickerId:null, searchRequestId:0, messageRequestId:0,
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
                        snippet(t) {return t?.relationships?.last_message?.attributes?.body || 'Open conversation';},
                        userName(u) {return u?.attributes?.name || u?.name || 'Teammate';},
                        isMine(m) {return String(m?.relationships?.sender?.data?.id || m?.attributes?.user_id)===String(this.myId);},
                        messageAttachments(m) { const a=m?.attributes || {}; return Array.isArray(a.attachments) && a.attachments.length ? a.attachments.filter(x=>x?.url) : (a.attachment?.url ? [a.attachment] : []); },
                        isImage(a) { return a?.type==='image' || /\.(png|jpe?g|gif|webp|avif|bmp)(\?|$)/i.test(a?.url || '') || /\.(png|jpe?g|gif|webp|avif|bmp)$/i.test(a?.original_name || ''); },
                        init() {this.connected=navigator.onLine;if('BroadcastChannel' in window){this.chatChannel=new BroadcastChannel('vortex-chat-'+this.myId);this.onChatSignal=(event)=>this.receiveChatUpdate(event);this.chatChannel.addEventListener('message',this.onChatSignal);}window.addEventListener('online',()=>{this.connected=true;if(this.open){this.load();if(this.selected)this.loadMessages();}});window.addEventListener('offline',()=>{this.connected=false;});try {this.pinnedIds=JSON.parse(localStorage.getItem('vortex-chat-pins-'+this.myId) || '[]');if(!Array.isArray(this.pinnedIds))this.pinnedIds=[];}catch(e){this.pinnedIds=[];} this.alertsEnabled=('Notification' in window && Notification.permission==='granted');this.onVisibilityChange=()=>{if(!document.hidden && this.open && navigator.onLine){this.load();if(this.selected)this.loadMessages();}};document.addEventListener('visibilitychange',this.onVisibilityChange);const chatId=new URLSearchParams(window.location.search).get('chat');if(chatId && /^[a-zA-Z0-9_-]{1,100}$/.test(chatId)){this.open=true;this.load().then(()=>{const thread=this.threads.find(t=>String(t.id)===chatId);if(thread)this.openThread(thread);else this.error='Conversation unavailable or access denied.';});this.startPolling();}},
                        isPinned(t) {return this.pinnedIds.includes(String(t?.id));},
                        togglePin() {if(!this.selected)return;const id=String(this.selected.id);this.pinnedIds=this.isPinned(this.selected)?this.pinnedIds.filter(x=>x!==id):[...this.pinnedIds,id];try{localStorage.setItem('vortex-chat-pins-'+this.myId,JSON.stringify(this.pinnedIds));}catch(e){}},
                        visibleThreads() {return this.threads.filter(t=>(!this.unreadOnly || this.unread(t)>0) && this.label(t).toLowerCase().includes(this.search.toLowerCase())).sort((a,b)=>Number(this.isPinned(b))-Number(this.isPinned(a)));},
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
                        formatTime(v) { if(!v) return ''; const d=new Date(v); return Number.isNaN(d.getTime()) ? '' : new Intl.DateTimeFormat(undefined,{month:'short',day:'numeric',hour:'numeric',minute:'2-digit'}).format(d); },
                        async load() {
                            if(this.loading) return;
                            this.loading=true;this.error='';
                            try {const list=this.items(await this.api('/conversations?per_page=50'));this.notifyNewUnread(list);this.threads=list;this.loaded=true;this.lastInboxRefresh=Date.now();}
                            catch(e) {this.error=e.message;} finally {this.loading=false;}
                        },
                        async openThread(t) {this.reactionPickerId=null;this.mentionQuery=null;this.groupMembers=[];this.showMembers=false;this.selected=t;if(t?.attributes?.conversation_type==='group')this.loadMentionMembers(t.id);this.copiedConversation=false;this.messageRequestId++;this.messages=[];this.messagesLoading=false;this.olderPage=1;this.hasOlder=false;this.historySearching=false;this.replyTo=null;this.messageSearch='';this.searchResults=[];this.searchRequestId++;this.lastMessageId=null;this.error='';this.files=[];this.draft='';this.showNew=false;await this.loadMessages();},
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
                                this.$nextTick(()=>{
                                    if(this.$refs.messagePane && (firstLoad || nearBottom))this.$refs.messagePane.scrollTop=this.$refs.messagePane.scrollHeight;
                                    this.lastMessageId=this.messages.at(-1)?.id;
                                });
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
                                this.draft='';this.files=[];this.replyTo=null;
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
                            try {this.users=this.items(await this.api('/users?per_page=100')).filter(u=>String(u.id)!==String(this.myId));}
                            catch(e) {this.error=e.message;}
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
                        startPolling() {if(this.timer)clearInterval(this.timer);this.timer=setInterval(()=>{if(!this.open || document.hidden || !navigator.onLine)return;if(this.selected)this.loadMessages();if(Date.now()-this.lastInboxRefresh>=15000)this.load();},5000);},
                        toggle() {
                            if(this.open) {this.close();return;}
                            this.open=true;if(!this.loaded)this.load();
                            this.startPolling();
                        },
                        close() {this.open=false;this.preview=null;if(this.timer){clearInterval(this.timer);this.timer=null;}},
                        destroy() {if(this.chatChannel){this.chatChannel.close();this.chatChannel=null;}if(this.timer){clearInterval(this.timer);this.timer=null;}if(this.onVisibilityChange)document.removeEventListener('visibilitychange',this.onVisibilityChange);}
                    };
                }
            </script>
        @endonce
    @else
        <livewire:quick-messages />
    @endif
@endif

@endif
