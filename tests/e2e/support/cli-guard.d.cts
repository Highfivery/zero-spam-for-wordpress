/** Types for cli-guard.cjs. */

/** Throws for WP-CLI arguments (without "wp") that would delete the plugin files. */
export function guardDestructive( args: string[] ): void;

/** Throws when a container shell script would delete the plugin files. */
export function guardShellScript( script: string ): void;
