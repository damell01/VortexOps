<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Support\NavVisibility;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;
    public const CORE_ROLES = ['admin', 'super_admin', 'streamer', 'fulfillment', 'fulfillment_admin'];
    protected static ?string $navigationLabel = 'Roles & Permissions';
    public const NOT_ROLE_CONTROLLED = NavVisibility::ALWAYS_AVAILABLE;

    public static function isCoreRole(string $name): bool { return in_array($name, self::CORE_ROLES, true); }
    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-shield-check'; }
    public static function getNavigationGroup(): string|\UnitEnum|null { return 'Settings'; }
    public static function getNavigationSort(): ?int { return 5; }
    public static function canAccess(): bool { return \App\Support\RoleAccess::grants(static::class) || (auth()->user()?->isOwner() ?? false); }
    public static function shouldRegisterNavigation(): bool { return static::canAccess(); }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Role details')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(255)->unique(ignoreRecord: true)->helperText('Internal role name, for example manager or fulfillment.'),
                TextInput::make('guard_name')->default('web')->required()->maxLength(255),
            ]),
            Section::make('Page Access')
                ->description('Choose one access level per page. Manage = view and edit. View only = can open the page but cannot edit where read-only enforcement is supported. Hidden = no access. Expand only the modules you need to change.')
                ->columnSpanFull()->schema(static::pageAccessSchema()),
            Section::make('Advanced Permissions')
                ->description('Optional Spatie permissions. Most day-to-day access should be managed with Page Access above.')
                ->collapsed()->columnSpanFull()->schema([
                    CheckboxList::make('permissions')->relationship('permissions', 'name')->searchable()->bulkToggleable()->columns(3)->noSearchResultsMessage('No permissions defined yet.'),
                ]),
        ]);
    }

    public static function roleControlledPages(): array { return array_keys(static::pageOptions()); }

    public static function pageOptions(): array
    {
        $panel = Filament::getCurrentPanel() ?? Filament::getDefaultPanel(); $opts=[];
        foreach ($panel->getResources() as $resource) { if ($resource===static::class) continue; try {$opts[$resource]=$resource::getNavigationLabel();} catch(\Throwable){} }
        foreach ($panel->getPages() as $page) { if (in_array($page,self::NOT_ROLE_CONTROLLED,true)) continue; try {$opts[$page]=$page::getNavigationLabel();} catch(\Throwable){} }
        asort($opts); return $opts;
    }

    public static function pagesByGroup(): array
    {
        $groups=[]; foreach(static::pageOptions() as $class=>$label){$groups[static::navigationGroupLabel($class)][$class]=$label;}
        foreach($groups as &$pages) asort($pages); unset($pages); ksort($groups); return $groups;
    }

    private static function navigationGroupLabel(string $class): string
    {
        try {$group=$class::getNavigationGroup();} catch(\Throwable){return 'General';}
        return match(true){is_string($group)&&$group!==''=>$group,$group instanceof \UnitEnum=>method_exists($group,'getLabel')?($group->getLabel()??$group->name):$group->name,default=>'General'};
    }

    public static function pageKey(string $class): string { return str_replace('\\','__',$class); }

    public static function pagePermsFormState(string $role, array $readonly): array
    {
        $configured=NavVisibility::hasExplicitVisibility($role); $visible=NavVisibility::visibleForRole($role); $hidden=NavVisibility::hiddenForRole($role); $state=[];
        foreach(static::pageOptions() as $class=>$label){$key=static::pageKey($class);$isVisible=$configured?in_array($class,$visible,true):!in_array($class,$hidden,true);$state[$key]=['access'=>!$isVisible?'hidden':(in_array($class,$readonly,true)?'view':'manage')];}
        return $state;
    }

    public static function pagePermsToLists(array $pagePerms): array
    {
        $hidden=[];$readonly=[];$visible=[];
        foreach(static::pageOptions() as $class=>$label){$key=static::pageKey($class);$entry=$pagePerms[$key]??[];$access=$entry['access']??'manage';if($access==='hidden'){$hidden[]=$class;continue;}$visible[]=$class;if($access==='view')$readonly[]=$class;}
        return [$hidden,$readonly,$visible];
    }

    public static function hasOwnAccessRule(string $class): bool
    {
        if(!method_exists($class,'canAccess'))return false;
        $definedIn=function(string $method)use($class):?string{try{return(new \ReflectionMethod($class,$method))->getFileName()?:null;}catch(\Throwable){return null;}};
        $isVendor=fn(?string $file):bool=>$file===null||str_contains($file,DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR);$canAccess=$definedIn('canAccess');if($isVendor($canAccess))return false;
        if($canAccess===(new \ReflectionClass(\App\Filament\Concerns\HasModuleAccess::class))->getFileName()){$check=$definedIn('passesModuleAccessCheck');return $check!==null&&$check!==$canAccess&&!$isVendor($check);}return true;
    }

    public static function neverAppearsInSidebar(string $class): bool { try{return !$class::shouldRegisterNavigation();}catch(\Throwable){return false;} }
    public static function disabledModuleFor(string $class): ?string { try{if(!in_array(\App\Filament\Concerns\HasModuleAccess::class,class_uses_recursive($class),true))return null;$slug=(new \ReflectionClass($class))->getStaticPropertyValue('moduleSlug',null);if(!is_string($slug)||$slug==='')return null;return \App\Support\AdminModules::isEnabled($slug)?null:$slug;}catch(\Throwable){return null;} }

    protected static function pageAccessLabel(string $class,string $label): \Illuminate\Support\HtmlString
    {
        $tags=''; if($module=static::disabledModuleFor($class))$tags.=static::tag('module off','This module is switched off for everyone.','rgb(239 68 68 / .14)','#b91c1c');
        if(static::neverAppearsInSidebar($class))$tags.=static::tag('sub-page','Opened from another page rather than the sidebar.','rgb(107 114 128 / .15)','#4b5563');
        if(static::hasOwnAccessRule($class))$tags.=static::tag('extra rule','This page also has a code-level access rule.','rgb(245 158 11 / .16)','#b45309');
        return new \Illuminate\Support\HtmlString('<strong>'.e($label).'</strong>'.$tags);
    }
    private static function tag(string $text,string $title,string $background,string $color): string{return ' <span title="'.e($title).'" style="margin-left:.375rem;padding:.0625rem .375rem;border-radius:9999px;background:'.$background.';color:'.$color.';font-size:.6875rem;font-weight:600;white-space:nowrap;">'.e($text).'</span>';}

    protected static function pageAccessSchema(): array
    {
        $sections=[];
        foreach(static::pagesByGroup() as $group=>$pages){$rows=[];foreach($pages as $class=>$label){$key=static::pageKey($class);$rows[]=Grid::make(12)->extraAttributes(['class'=>'vx-role-access-row'])->schema([
            Placeholder::make("page_label_{$key}")->hiddenLabel()->content(static::pageAccessLabel($class,$label))->columnSpan(['default'=>12,'md'=>5]),
            Radio::make("page_perms.{$key}.access")->hiddenLabel()->options(['manage'=>'Manage','view'=>'View only','hidden'=>'Hidden'])->inline()->inlineLabel(false)->default('manage')->dehydrated(false)->columnSpan(['default'=>12,'md'=>7]),
        ]);}
        $sections[]=Section::make($group)->description(count($pages).' pages')->compact()->collapsible()->collapsed()->schema($rows);}
        return $sections;
    }

    public static function table(Table $table): Table
    {
        return $table->striped()->emptyStateHeading('No roles')->emptyStateDescription('Create a role to group permissions for your team.')->emptyStateIcon('heroicon-o-shield-check')->columns([
            TextColumn::make('name')->searchable()->sortable()->weight('bold'),TextColumn::make('permissions_count')->counts('permissions')->label('Permissions')->badge()->color('info'),TextColumn::make('users_count')->counts('users')->label('Users')->badge(),TextColumn::make('guard_name')->label('Guard')->toggleable(isToggledHiddenByDefault:true),
        ])->defaultSort('name')->actions([\Filament\Actions\EditAction::make()->iconButton(),\Filament\Actions\DeleteAction::make()->iconButton()->visible(fn(Role $record)=>!static::isCoreRole($record->name))]);
    }
    public static function getPages(): array{return ['index'=>Pages\ListRoles::route('/'),'create'=>Pages\CreateRole::route('/create'),'edit'=>Pages\EditRole::route('/{record}/edit')];}
}
