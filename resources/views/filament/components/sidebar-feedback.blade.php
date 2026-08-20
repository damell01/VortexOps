{{-- Feedback, as a navigation item.

     It used to float over the bottom-right corner of every page — permanently
     on top of table rows, modal footers and the last line of every form, which
     is a lot of screen to spend on something used occasionally. In the sidebar
     it sits where somebody would go looking for it and covers nothing.

     Styled from the sidebar's own item classes rather than hand-rolled, so it
     inherits collapsed state, hover and spacing along with the real links. It
     dispatches rather than navigates: the widget is already mounted for the
     whole panel, so opening it needs an event, not a page. --}}
<li class="fi-sidebar-item vx-sidebar-feedback">
    <button
        type="button"
        x-data
        @click="window.dispatchEvent(new Event('open-feedback'))"
        class="fi-sidebar-item-btn"
        title="Leave feedback"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="fi-icon fi-size-md shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
        </svg>
        <span class="fi-sidebar-item-label">Feedback</span>
    </button>
</li>
