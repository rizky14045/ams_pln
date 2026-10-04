<?php

namespace App\Http\Middleware;

use Closure;
use DB;
use Session;

/**
 * Whitelist akses web admin: hanya cms_users dengan akses_web = 1 yang boleh
 * login dan tetap memakai session web. Dipasang di group 'web', jadi berlaku
 * untuk semua halaman /admin dan route web lain. Route API tidak terpengaruh.
 */
class CBWebWhitelist
{
    public function handle($request, Closure $next)
    {
        $loginPath = trim(config('crudbooster.ADMIN_PATH', 'admin'), '/').'/login';

        // 1. Cegah login oleh user yang tidak di-whitelist
        if ($request->isMethod('post') && $request->is($loginPath)) {
            $email = $request->input('email');
            $user = $email
                ? DB::table('cms_users')->where('email', $email)->whereNull('deleted_at')->first()
                : null;

            if ($user && !$user->akses_web) {
                return redirect()->route('getLogin')->with([
                    'message' => 'Akun Anda tidak diizinkan mengakses web. Hubungi admin.',
                    'message_type' => 'danger',
                ]);
            }

            return $next($request);
        }

        // 2. Session yang sudah aktif dicabut bila whitelist-nya dihapus
        $adminId = Session::get('admin_id');
        if ($adminId) {
            $allowed = DB::table('cms_users')->where('id', $adminId)->value('akses_web');

            if (!$allowed) {
                Session::flush();

                return redirect()->route('getLogin')->with([
                    'message' => 'Akses web Anda sudah dicabut. Hubungi admin.',
                    'message_type' => 'danger',
                ]);
            }
        }

        return $next($request);
    }
}
