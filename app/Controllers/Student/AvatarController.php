<?php

namespace App\Controllers\Student;

use App\Controllers\AvatarController as BaseAvatarController;
use App\Services\VoterType;

/**
 * Avatar siswa (Stage 26): kolom avatar_* di students untuk student_id dari
 * sesi. Route berada di grup filter studentauth (app/Config/Routes.php).
 */
class AvatarController extends BaseAvatarController
{
    protected function voterType(): VoterType
    {
        return VoterType::Student;
    }
}
