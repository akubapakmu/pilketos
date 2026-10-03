<?php

namespace App\Controllers\Teacher;

use App\Controllers\BaseController;
use App\Libraries\CandidateTheme;
use App\Models\TeacherModel;
use App\Services\VoterType;
use Config\Homepage;

class DashboardController extends BaseController
{
    public function index()
    {
        // Data selalu diambil dari database (bukan dari sesi) agar selalu terbaru.
        // TeacherAuthFilter sudah menjamin guru ada dan aktif.
        $teacherId = (int) session()->get('teacher_id');
        $state     = service('voting')->ballotState(VoterType::Teacher, $teacherId);
        $voter     = model(TeacherModel::class)->findActive($teacherId);

        return view('teacher/dashboard', [
            'title'     => 'Dasbor Guru',
            'type'      => VoterType::Teacher,
            'teacher'   => $voter,
            'election'  => $state['election'],
            'vote'      => $state['vote'],
            'candidate' => $state['candidate'] === null ? null : CandidateTheme::present($state['candidate']),
            'canVote'   => $state['canVote'],
            // Stage 26: kartu avatar Living Election Wall (tidak tampil bila wall dimatikan)
            'avatar'    => config(Homepage::class)->electionWall ? service('voterAvatars')->present(VoterType::Teacher, $voter) : null,
        ]);
    }
}
