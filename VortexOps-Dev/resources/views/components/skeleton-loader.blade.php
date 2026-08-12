{{-- Skeleton Loading Component --}}

@if($type === 'card')
    <div class="skeleton-card skeleton">
        <div class="skeleton-title skeleton" style="width: 60%; height: 20px; margin-bottom: 16px;"></div>
        <div class="skeleton-text skeleton" style="width: 100%; height: 16px; margin-bottom: 8px;"></div>
        <div class="skeleton-text skeleton" style="width: 85%; height: 16px;"></div>
    </div>

@elseif($type === 'table')
    <div class="skeleton-table">
        <div class="skeleton-table-header">
            @for($i = 0; $i < ($columns ?? 4); $i++)
                <div class="skeleton-table-header-cell skeleton"></div>
            @endfor
        </div>
        @for($i = 0; $i < ($rows ?? 3); $i++)
            <div class="skeleton-table-row">
                @for($j = 0; $j < ($columns ?? 4); $j++)
                    <div class="skeleton-table-cell skeleton"></div>
                @endfor
            </div>
        @endfor
    </div>

@elseif($type === 'list')
    @for($i = 0; $i < ($items ?? 3); $i++)
        <div class="skeleton-list-item skeleton">
            <div class="skeleton-list-item-header">
                <div class="skeleton-list-item-avatar skeleton"></div>
                <div class="skeleton-list-item-text">
                    <div class="skeleton-list-item-title skeleton"></div>
                    <div class="skeleton-list-item-subtitle skeleton"></div>
                </div>
            </div>
        </div>
    @endfor

@elseif($type === 'form')
    <div class="skeleton-form">
        @for($i = 0; $i < ($fields ?? 3); $i++)
            <div class="skeleton-form-group">
                <div class="skeleton-form-label skeleton" style="width: 25%; height: 14px; margin-bottom: 8px;"></div>
                <div class="skeleton-form-input skeleton"></div>
            </div>
        @endfor
    </div>

@elseif($type === 'dashboard')
    @for($i = 0; $i < ($cards ?? 4); $i++)
        <div class="skeleton-dashboard-card skeleton" style="padding: 16px; border-radius: 12px; margin-bottom: 12px;">
            <div class="skeleton-dashboard-card-title skeleton" style="width: 40%; height: 16px; margin-bottom: 12px;"></div>
            <div class="skeleton-dashboard-card-value skeleton" style="width: 60%; height: 32px; margin-bottom: 8px;"></div>
            <div class="skeleton-dashboard-card-footer skeleton" style="width: 50%; height: 12px;"></div>
        </div>
    @endfor

@elseif($type === 'modal')
    <div class="skeleton-modal-header skeleton" style="width: 40%; height: 24px; margin-bottom: 16px;"></div>
    <div class="skeleton-modal-content">
        @for($i = 0; $i < 3; $i++)
            <div class="skeleton" style="height: 16px; width: 100%; margin-bottom: 8px;"></div>
        @endfor
    </div>
    <div class="skeleton-modal-footer" style="display: flex; gap: 8px; margin-top: 16px;">
        <div class="skeleton-modal-footer-button skeleton" style="flex: 1;"></div>
        <div class="skeleton-modal-footer-button skeleton" style="flex: 1;"></div>
    </div>

@else
    {{-- Default: Simple skeleton box --}}
    <div class="skeleton" style="height: {{ $height ?? '40px' }}; width: 100%; margin-bottom: 12px;"></div>
@endif
