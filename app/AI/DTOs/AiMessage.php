<?php

namespace App\AI\DTOs;

/**
 * A single turn in an AI conversation, provider-agnostic. Images are base64
 * strings carried alongside a user turn for vision-capable models.
 */
final class AiMessage
{
    /** @param string[] $images */
    public function __construct(
        public readonly string $role,
        public readonly string $content,
        public readonly array $images = [],
    ) {}

    public static function system(string $content): self
    {
        return new self('system', $content);
    }

    /** @param string[] $images */
    public static function user(string $content, array $images = []): self
    {
        return new self('user', $content, $images);
    }

    public static function assistant(string $content): self
    {
        return new self('assistant', $content);
    }

    /**
     * Wire shape consumed by providers. Images are only included when present so
     * text-only turns stay lean.
     *
     * @return array{role:string,content:string,images?:string[]}
     */
    public function toArray(): array
    {
        $out = ['role' => $this->role, 'content' => $this->content];

        if ($this->images !== []) {
            $out['images'] = $this->images;
        }

        return $out;
    }

    /**
     * Normalise a mixed list of AiMessage|array into wire arrays. Lets callers
     * pass either DTOs or plain role/content arrays.
     *
     * @param  array<int, self|array{role:string,content:string,images?:string[]}> $messages
     * @return list<array{role:string,content:string,images?:string[]}>
     */
    public static function normalizeMany(array $messages): array
    {
        return array_map(
            fn ($m) => $m instanceof self ? $m->toArray() : $m,
            array_values($messages),
        );
    }
}
