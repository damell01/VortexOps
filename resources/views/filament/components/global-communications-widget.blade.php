@if(\App\Services\TeamCommunications::canAccess(auth()->user()))
    @if(config('chatify-preview.enabled', false))
        <div class="vx-chatify-shell" x-data="vortexChatifyWidget()" x-on:keydown.escape.window="close()">
            <button type="button" class="vx-chatify-launch" x-on:click="toggle()" x-bind:aria-expanded="String(open)" aria-controls="vx-chatify-drawer" aria-label="Open messages">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8A8.5 8.5 0 0 1 8.7 3.9a8.38 8.38 0 0 1 3.8-.9L13 3a8.48 8.48 0 0 1 8 8v.5Z"/></svg>
                <span>Messages</span><span class="vx-chatify-launch-unread" x-show="totalUnread()>0" x-text="totalUnread()" aria-label="Unread messages"></span>
            </button>
            <section id="vx-chatify-drawer" class="vx-chatify-drawer vx-chatify-native" x-show="open" x-cloak role="dialog" aria-modal="false" aria-label="Team messages">
                <header class="vx-chatify-drawer-head">
                    <div><strong>Messages</strong><small>Vortex Ops team chat</small></div>
                    <div class="vx-chatify-drawer-actions">
                        <button type="button" x-on:click="selected ? loadMessages() : load()" title="Refresh" aria-label="Refresh messages"><x-filament::icon icon="heroicon-o-arrow-path" /></button>
                        <button type="button" x-on:click="close()" title="Close messages" aria-label="Close messages"><x-filament::icon icon="heroicon-o-x-mark" /></button>
                    </div>
                </header>
                <div class="vx-chatify-native-body" x-show="!selected">
                    <div class="vx-chatify-native-search"><input type="search" x-model="search" placeholder="Search conversations…" aria-label="Search conversations"></div><div class="vx-chatify-inbox-toolbar"><button type="button" x-on:click="unreadOnly=!unreadOnly" :aria-pressed="String(unreadOnly)" :class="unreadOnly ? 'is-active' : ''">Unread only</button><span x-text="totalUnread() ? totalUnread()+' unread' : 'All caught up'"></span><button type="button" x-on:click="enableAlerts()" :disabled="alertsEnabled" x-text="alertsEnabled ? 'Alerts on' : 'Enable alerts'" title="Browser notifications while the messenger is open"></button></div>
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
                        <strong x-text="selected ? label(selected) : ''"></strong><button type="button" x-on:click="togglePin()" :aria-pressed="String(selected ? isPinned(selected) : false)" :title="selected && isPinned(selected) ? 'Unpin chat' : 'Pin chat'" aria-label="Pin or unpin chat">📌</button><button type="button" x-on:click="loadMessages()" aria-label="Refresh conversation">↻</button>
                    </div>
                    <div class="vx-chatify-message-search"><input type="search" x-model="messageSearch" placeholder="Find in this conversation…" aria-label="Search messages in conversation"><span x-text="messageSearch ? filteredMessages().length + ' matches' : ''"></span></div><div class="vx-chatify-message-pane" x-ref="messagePane" aria-live="polite">
                        <p class="vx-chatify-native-hint" x-show="messagesLoading && !messages.length">Loading messages…</p>
                        <template x-for="(message,index) in filteredMessages()" :key="message.id">
                            <div class="vx-chatify-message-group" :class="isMine(message) ? 'is-mine' : ''"><div class="vx-chatify-date-separator" x-show="showDate(message,index)" x-text="formatDate(message.attributes?.created_at)"></div><div class="vx-chatify-message" :class="isMine(message) ? 'is-mine' : ''">
                                <span x-show="message.attributes?.body" x-text="message.attributes?.body"></span>
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
                                <small class="vx-chatify-message-meta"><time :datetime="message.attributes?.created_at" x-text="formatTime(message.attributes?.created_at)"></time><span x-show="isMine(message)"> · Sent</span><span x-show="!isMine(message) && message.attributes?.read === true"> · Read by you</span></small>
                            </div></div>
                        </template>
                    </div>
                    <p class="vx-chatify-native-error" x-show="error" x-text="error" role="alert"></p>
                    <div class="vx-chatify-attachments-selected" x-show="files.length" x-cloak>
                        <template x-for="(file,index) in files" :key="index">
                            <span><span x-text="file.name"></span><button type="button" x-on:click="removeFile(index)" aria-label="Remove attachment">×</button></span>
                        </template>
                    </div>
                    <form class="vx-chatify-composer" x-on:submit.prevent="send()" x-on:dragover.prevent="dragging=true" x-on:dragleave.prevent="dragging=false" x-on:drop.prevent="dragging=false;addFiles($event.dataTransfer.files)" :class="dragging ? 'is-dragging' : ''">
                        <label class="vx-chatify-attach-button" title="Attach photos or files" aria-label="Attach photos or files">＋<input type="file" multiple x-ref="fileInput" x-on:change="addFiles($event.target.files);$event.target.value=''" accept="image/*,.pdf,.txt,.doc,.docx,.xls,.xlsx,.csv,.zip"></label>
                        <textarea rows="1" x-model="draft" placeholder="Write a message…" aria-label="Message" maxlength="10000" x-on:keydown.enter="if(!$event.shiftKey){$event.preventDefault();send()}"></textarea>
                        <button type="submit" :disabled="sending || (!draft.trim() && !files.length)" x-text="sending ? 'Sending…' : 'Send'"></button>
                    </form>
                </div>
                <div class="vx-chatify-preview" x-show="preview" x-cloak x-on:click.self="preview=null" role="dialog" aria-label="Image preview"><button type="button" x-on:click="preview=null" aria-label="Close image preview">✕</button><img :src="preview || ''" alt="Expanded chat image"></div>
                <footer class="vx-chatify-native-footer" x-show="!selected">
                    <button type="button" x-on:click="showNew=!showNew; if(showNew) loadUsers()">New message or group chat <span aria-hidden="true">＋</span></button>
                </footer>
                <div class="vx-chatify-new-overlay" x-show="showNew && !selected" x-cloak>
                    <div class="vx-chatify-conversation-head"><strong>New conversation</strong><button type="button" x-on:click="showNew=false" aria-label="Cancel">✕</button></div>
                    <input type="search" x-model="userSearch" placeholder="Find a teammate…" aria-label="Find a teammate">
                    <div class="vx-chatify-user-list">
                        <template x-for="user in users.filter(u => userName(u).toLowerCase().includes(userSearch.toLowerCase()))" :key="user.id">
                            <label><input type="checkbox" :value="String(user.id)" x-model="recipients"><span x-text="userName(user)"></span></label>
                        </template>
                    </div>
                    <input type="text" x-show="recipients.length > 1" x-model="groupName" placeholder="Group name" aria-label="Group name">
                    <p class="vx-chatify-native-error" x-show="error" x-text="error"></p>
                    <button type="button" class="vx-chatify-new-create" x-on:click="createConversation()" :disabled="!recipients.length || creating" x-text="creating ? 'Creating…' : 'Start conversation'"></button>
                </div>
            </section>
        </div>
        @once
            <script>
                function vortexChatifyWidget() {
                    return {
                        open:false, loaded:false, loading:false, error:'', threads:[], search:'', selected:null, messages:[],
                        messagesLoading:false, draft:'', sending:false, timer:null, showNew:false, users:[], userSearch:'', recipients:[], groupName:'', creating:false, preview:null, files:[], dragging:false, lastMessageId:null, unreadOnly:false, messageSearch:'', pinnedIds:[], alertsEnabled:false, unreadSnapshot:null,
                        base:@json(url('/api/chatify/v1')), myId:@json((int) auth()->id()),
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
                        init() {try {this.pinnedIds=JSON.parse(localStorage.getItem('vortex-chat-pins-'+this.myId) || '[]');if(!Array.isArray(this.pinnedIds))this.pinnedIds=[];}catch(e){this.pinnedIds=[];} this.alertsEnabled=('Notification' in window && Notification.permission==='granted');},
                        isPinned(t) {return this.pinnedIds.includes(String(t?.id));},
                        togglePin() {if(!this.selected)return;const id=String(this.selected.id);this.pinnedIds=this.isPinned(this.selected)?this.pinnedIds.filter(x=>x!==id):[...this.pinnedIds,id];try{localStorage.setItem('vortex-chat-pins-'+this.myId,JSON.stringify(this.pinnedIds));}catch(e){}},
                        visibleThreads() {return this.threads.filter(t=>(!this.unreadOnly || this.unread(t)>0) && this.label(t).toLowerCase().includes(this.search.toLowerCase())).sort((a,b)=>Number(this.isPinned(b))-Number(this.isPinned(a)));},
                        filteredMessages() {const q=this.messageSearch.trim().toLowerCase();return q ? this.messages.filter(m=>String(m?.attributes?.body || '').toLowerCase().includes(q)) : this.messages;},
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
                            try {const list=this.items(await this.api('/conversations?per_page=50'));this.notifyNewUnread(list);this.threads=list;this.loaded=true;}
                            catch(e) {this.error=e.message;} finally {this.loading=false;}
                        },
                        async openThread(t) {this.selected=t;this.messages=[];this.messageSearch='';this.lastMessageId=null;this.error='';this.files=[];this.draft='';this.showNew=false;await this.loadMessages();},
                        async loadMessages() {
                            if(!this.selected || this.messagesLoading) return;
                            const id=this.selected.id;
                            this.messagesLoading=true;
                            try {
                                const data=await this.api('/conversations/'+encodeURIComponent(id)+'/messages?per_page=50');
                                if(this.selected?.id!==id) return;
                                const list=this.items(data); this.messages=list.slice().sort((a,b)=>new Date(a.attributes?.created_at || 0)-new Date(b.attributes?.created_at || 0));
                                this.$nextTick(()=>{const pane=this.$refs.messagePane;if(pane && (!this.lastMessageId || pane.scrollHeight-pane.scrollTop-pane.clientHeight<130)) pane.scrollTop=pane.scrollHeight;this.lastMessageId=this.messages.at(-1)?.id;});
                                this.error='';
                            } catch(e) {this.error=e.message;} finally {this.messagesLoading=false;}
                        },
                        addFiles(incoming) {
                            const chosen=Array.from(incoming || []);
                            for(const file of chosen) {
                                if(file.size>20*1024*1024) {this.error='Each attachment must be 20 MB or smaller.';continue;}
                                if(this.files.length>=5) {this.error='Attach up to five files per message.';break;}
                                this.files.push(file);
                            }
                        },
                        removeFile(index) {this.files.splice(index,1);},
                        async send() {
                            if(!this.selected || (!this.draft.trim() && !this.files.length) || this.sending) return;
                            this.sending=true;this.error='';
                            const conversationId=this.selected.id;
                            try {
                                const body=new FormData();
                                if(this.draft.trim()) body.append('body',this.draft.trim());
                                this.files.forEach(file=>body.append('attachments[]',file,file.name));
                                await this.api('/conversations/'+encodeURIComponent(conversationId)+'/messages',{method:'POST',body});
                                this.draft='';this.files=[];
                                await this.loadMessages();
                                this.load();
                            } catch(e) {this.error=e.message;} finally {this.sending=false;}
                        },
                        async loadUsers() {
                            try {this.users=this.items(await this.api('/users?per_page=100')).filter(u=>String(u.id)!==String(this.myId));}
                            catch(e) {this.error=e.message;}
                        },
                        async createConversation() {
                            if(!this.recipients.length || this.creating) return;
                            this.creating=true;this.error='';
                            try {
                                const group=this.recipients.length>1;
                                const path=group?'/conversations/group':'/conversations/direct';
                                const body=group?{name:this.groupName.trim() || 'Group chat',user_ids:this.recipients.map(Number)}:{user_id:Number(this.recipients[0])};
                                const data=await this.api(path,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
                                this.showNew=false;this.recipients=[];this.groupName='';
                                await this.load();
                                const t=data?.data;
                                if(t?.id) await this.openThread(t);
                            } catch(e) {this.error=e.message;} finally {this.creating=false;}
                        },
                        toggle() {
                            if(this.open) {this.close();return;}
                            this.open=true;if(!this.loaded)this.load();
                            this.timer=setInterval(()=>{if(this.open){if(this.selected)this.loadMessages();this.load();}},15000);
                        },
                        close() {this.open=false;this.preview=null;if(this.timer){clearInterval(this.timer);this.timer=null;}}
                    };
                }
            </script>
        @endonce
    @else
        <livewire:quick-messages />
    @endif
@endif
