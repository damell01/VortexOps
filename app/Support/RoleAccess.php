<?php

namespace App\Support;

use App\Models\User;

/**
 * Makes an explicit grant on Roles & Permissions mean what it says.
 *
 * Page access was decided in two places that did not agree. The Roles &
 * Permissions screen wrote an allow-list per role, and every resource and page
 * separately hardcoded who it was for — usually isAdmin(). The hardcode won,
 * so ticking a page for a custom role changed nothing: 47 of the panel's 82
 * pages still refused a role that had been granted all of them, and every
 * Shield permission besides. The screen's own help text admitted it ("will
 * need code changes before a custom role can safely use it").
 *
 * The rule now: if a role the user holds explicitly names this page in its
 * visible list, that is the answer. The hardcoded check is the fallback for
 * roles with no explicit list — which is every built-in role today, so
 * admin, streamer and fulfillment behave exactly as before.
 *
 * Two things a grant deliberately cannot do:
 *
 *  - Reach into a disabled module. That switch is the owner turning off a
 *    whole area of the product, not a per-role opinion.
 *  - Change what a query returns. Row scoping lives in getEloquentQuery() and
 *    is untouched: a streamer still sees only their own shows. A custom role
 *    is not a streamer, so "their own rows" means nothing for it — granting a
 *    custom role a page shows it that page's data, which is what granting it
 *    is for. Grant deliberately.
 */
final class RoleAccess
{
    /** @var array<class-string, string|null> */
    private static array $moduleSlugMemo = [];

    /**
     * Does a role the signed-in user holds explicitly grant this page?
     *
     * @param  class-string  $class
     */
    public static function grants(string $class, ?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        $slug = self::moduleSlugFor($class);

        if ($slug !== null && ! AdminModules::isEnabled($slug)) {
            return false;
        }

        return NavVisibility::isExplicitlyGrantedTo($class, $user);
    }

    /**
     * Does a granting role also allow changing what is on the page?
     *
     * The Roles & Permissions screen offers "Visible" and "Can Edit" per page.
     * Visible was the only one that did anything: readonlyForRole() was
     * written by that screen and read back by that same screen, and nothing
     * else ever asked. Can Edit was a checkbox with no effect.
     *
     * Most permissive role wins, the same way visibility does. A role that
     * grants the page without marking it readonly allows editing, even if
     * another of the user's roles marks it readonly — otherwise adding a
     * narrow second role would quietly take away what the first one gave.
     *
     * @param  class-string  $class
     */
    public static function allowsEditing(string $class, ?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user || ! self::grants($class, $user)) {
            return false;
        }

        foreach ($user->getRoleNames() as $role) {
            if (! NavVisibility::hasExplicitVisibility($role)) {
                continue;
            }

            if (! in_array($class, NavVisibility::visibleForRole($role), true)) {
                continue;
            }

            if (! in_array($class, NavVisibility::readonlyForRole($role), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The module a page belongs to, read once per class.
     *
     * Reflection because $moduleSlug is protected and this runs for every
     * item in the sidebar on every request; without the memo that is eighty
     * reflections a page render.
     *
     * @param  class-string  $class
     */
    private static function moduleSlugFor(string $class): ?string
    {
        if (array_key_exists($class, self::$moduleSlugMemo)) {
            return self::$moduleSlugMemo[$class];
        }

        $slug = null;

        if (property_exists($class, 'moduleSlug')) {
            try {
                $property = new \ReflectionProperty($class, 'moduleSlug');
                $property->setAccessible(true);
                $value = $property->getValue();
                $slug  = is_string($value) && $value !== '' ? $value : null;
            } catch (\Throwable) {
                $slug = null;
            }
        }

        return self::$moduleSlugMemo[$class] = $slug;
    }

    public static function flushMemo(): void
    {
        self::$moduleSlugMemo = [];
    }
}
