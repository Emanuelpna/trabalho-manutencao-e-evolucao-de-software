<?php

namespace SegWeb\Services;

use Auth;

class UserService
{
    public function getUser()
    {
        if (Auth::check()) {
            $user = Auth::user();
            $user_id = $user->id;
        } else {
            $user_id = 0;
        }

        return $user_id;
    }
}
