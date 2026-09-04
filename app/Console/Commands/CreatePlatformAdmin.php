<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Creates a platform administrator, or promotes an existing user to one.
 *
 * A platform admin can review payment profiles and impersonate every
 * organization on the deployment, so there is deliberately no self-service
 * route to the flag: it is granted here, on the server, by someone with shell
 * access. The password is typed at a prompt rather than passed as an option so
 * it never lands in the server's shell history.
 */
class CreatePlatformAdmin extends Command
{
    protected $signature = 'hotfii:create-admin
        {email? : The administrator\'s email address}
        {--name= : Display name, used when the account is created}';

    protected $description = 'Create a platform administrator, or promote an existing user to one';

    public function handle(): int
    {
        $email = Str::lower(trim((string) ($this->argument('email') ?: $this->ask('Email address'))));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']])->fails()) {
            $this->error('That is not a valid email address.');

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();

        if ($existing) {
            $this->line(sprintf(
                'Found <info>%s</info>, %s a platform admin.',
                $existing->name,
                $existing->is_platform_admin ? 'already' : 'not yet',
            ));
        }

        $password = null;

        // An existing account belongs to whoever set it up, so keeping their
        // password is the default; promoting them does not require resetting it.
        if ($existing === null || $this->confirm('Set a new password?', false)) {
            $password = $this->askForPassword();

            if ($password === null) {
                $this->error('No usable password was given, so nothing was changed.');

                return self::FAILURE;
            }
        }

        $attributes = ['is_platform_admin' => true];

        if ($password !== null) {
            $attributes['password'] = $password;
        }

        if ($existing === null) {
            $attributes['name'] = (string) ($this->option('name') ?: $this->ask('Display name', 'HotFii Platform Admin'));
        } elseif (filled($this->option('name'))) {
            $attributes['name'] = (string) $this->option('name');
        }

        $wasAdmin = (bool) $existing?->is_platform_admin;

        $user = User::updateOrCreate(['email' => $email], $attributes);

        // The platform routes sit behind the `verified` middleware, and a fresh
        // deployment may not have working mail yet, so an account provisioned
        // from the server is trusted as verified. email_verified_at is
        // deliberately not mass-assignable, hence the model's own method.
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        AuditLog::create([
            // Run from a console, so there is no acting user to record.
            'user_id' => null,
            'action' => $wasAdmin ? 'platform-admin.updated' : 'platform-admin.granted',
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'reason' => 'Granted with hotfii:create-admin on the server.',
            'after' => ['email' => $email, 'password_changed' => $password !== null],
        ]);

        $this->info(sprintf(
            '%s is a platform admin. Sign in and open %s/platform.',
            $email,
            rtrim((string) config('app.url'), '/'),
        ));

        return self::SUCCESS;
    }

    /**
     * Asks for the password twice, hidden, holding it to the same strength rule
     * the sign-up form uses. Returns null when no valid password was given.
     */
    private function askForPassword(): ?string
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $password = (string) $this->secret('Password');

            if ($password !== (string) $this->secret('Confirm password')) {
                $this->warn('The two passwords did not match.');

                continue;
            }

            $validator = Validator::make(
                ['password' => $password],
                ['password' => ['required', Password::defaults()]],
            );

            if ($validator->fails()) {
                $this->warn((string) $validator->errors()->first('password'));

                continue;
            }

            return $password;
        }

        return null;
    }
}
