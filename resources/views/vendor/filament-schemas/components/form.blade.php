{{-- Filament's form, with the browser's own field checks off (novalidate). Those
     checks block the submit before anything reaches the server, and a field
     failing one inside a closed section or another tab was never shown, so Save
     looked dead. Every rule is still enforced on the server, which marks the
     field and says why. --}}
<form
    novalidate
    {{
        $attributes
            ->merge([
                'id' => $getId(),
                'wire:submit' => $getLivewireSubmitHandler(),
            ], escape: false)
            ->merge($getExtraAttributes(), escape: false)
            ->class([
                'fi-sc-form',
                'fi-dense' => $isDense(),
            ])
    }}
>
    {{ $getChildSchema($schemaComponent::HEADER_SCHEMA_KEY) }}

    {{ $getChildSchema() }}

    {{ $getChildSchema($schemaComponent::FOOTER_SCHEMA_KEY) }}
</form>
