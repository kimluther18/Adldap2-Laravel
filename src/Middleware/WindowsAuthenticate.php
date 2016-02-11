<?php

namespace Adldap\Laravel\Middleware;

use Adldap\Laravel\Traits\ImportsUsers;
use Adldap\Models\User;
use Adldap\Schemas\ActiveDirectory;
use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class WindowsAuthenticate
{
    use ImportsUsers;

    /**
     * The authenticator implementation.
     *
     * @var \Illuminate\Contracts\Auth\Guard
     */
    protected $auth;

    /**
     * Create a new filter instance.
     *
     * @param Guard $auth
     */
    public function __construct(Guard $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Retrieve the SSO login attribute.
        $auth = $this->getWindowsAuthAttribute();

        $key = key($auth);

        // Handle Windows Authentication.
        if ($account = $request->server($auth[$key])) {
            // Usernames will be prefixed with their domain,
            // we just need their account name.
            list($domain, $username) = explode('\\', $account);

            // Create a new user LDAP user query.
            $query = $this->newAdldapUserQuery();

            // Get the input key
            $key = key($auth);

            // Filter the query by the username attribute
            $query->whereEquals($key, $username);

            // Retrieve the first user result
            $user = $query->first();

            if ($user instanceof User) {
                $model = $this->getModelFromAdldap($user, str_random());

                if ($model instanceof Model && $this->auth->guest()) {
                    // Double check user instance before logging them in.
                    $this->auth->login($model);
                }
            }
        }

        return $next($request);
    }

    /**
     * Returns a new auth model instance.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function createModel()
    {
        $model = $this->auth->getProvider()->getModel();

        return new $model();
    }

    /**
     * Returns the windows authentication attribute.
     *
     * @return string
     */
    protected function getWindowsAuthAttribute()
    {
        return Config::get('adldap_auth.windows_auth_attribute', [ActiveDirectory::ACCOUNT_NAME => 'AUTH_USER']);
    }
}
