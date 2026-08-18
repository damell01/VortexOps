@extends('errors.layout')
@section('code', '403')
@section('title', 'Access Denied')
@section('message', $exception?->getMessage() ?: "You don't have permission to view this page. Contact an admin if you think this is a mistake.")

{{-- Which rule refused, in the page itself.
     A 403 with no provenance costs an investigation every time one appears:
     the visibility gate, a page's own owner/admin check, and a switched-off
     module all render this identical screen, and the only way to tell them
     apart was to go and read the code. The details below turn a screenshot
     into a report. Roles and path only — nothing here is not already known to
     the person looking at it, and the page sits behind auth. --}}
@section('details')
    @php
        $vxUser = auth()->user();
        $vxRoles = $vxUser && method_exists($vxUser, 'getRoleNames')
            ? $vxUser->getRoleNames()->implode(', ')
            : '';
    @endphp
    <dl class="vx-403-details">
        <div><dt>Path</dt><dd>{{ request()->path() }}</dd></div>
        @if ($vxUser)
            <div><dt>Signed in as</dt><dd>{{ $vxUser->email }}</dd></div>
            <div>
                <dt>Roles</dt>
                <dd>{{ $vxRoles !== '' ? $vxRoles : 'none assigned' }}{{ $vxUser->isOwner() ? ' · owner' : '' }}</dd>
            </div>
        @else
            <div><dt>Session</dt><dd>signed out — this is usually an expired session rather than a permissions problem</dd></div>
        @endif
    </dl>
@endsection
