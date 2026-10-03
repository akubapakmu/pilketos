<?php

namespace App\Controllers\Student;

use App\Controllers\BaseController;
use App\Libraries\CandidateTheme;
use App\Models\StudentModel;
use App\Services\VoterType;
use Config\Homepage;

class DashboardController extends BaseController
{
    public function index()
    {
        // Data selalu diambil dari database (bukan dari sesi) agar selalu terbaru.
        // StudentAuthFilter sudah menjamin siswa ada dan aktif.
        $studentId = (int) session()->get('student_id');
        $state     = service('voting')->ballotState(VoterType::Student, $studentId);
        $voter     = model(StudentModel::class)->findActive($studentId);

        return view('student/dashboard', [
            'title'     => 'Dasbor Siswa',
            'type'      => VoterType::Student,
            'student'   => $voter,
            'election'  => $state['election'],
            'vote'      => $state['vote'],
            'candidate' => $state['candidate'] === null ? null : CandidateTheme::present($state['candidate']),
            'canVote'   => $state['canVote'],
            // Stage 26: kartu avatar Living Election Wall (tidak tampil bila wall dimatikan)
            'avatar'    => config(Homepage::class)->electionWall ? service('voterAvatars')->present(VoterType::Student, $voter) : null,
        ]);
    }
}
