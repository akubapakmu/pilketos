<?php

namespace App\Controllers\Teacher;

use App\Controllers\AvatarController as BaseAvatarController;
use App\Services\VoterType;

/**
 * Avatar guru (Stage 26): kolom avatar_* di teachers untuk teacher_id dari
 * sesi. Route berada di grup filter teacherauth (app/Config/Routes.php).
 */
class AvatarController extends BaseAvatarController
{
    protected function voterType(): VoterType
    {
        return VoterType::Teacher;
    }
}
