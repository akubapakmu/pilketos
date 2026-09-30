<?php

use App\Database\Seeds\DatabaseSeeder;
use App\Models\CandidateModel;
use App\Models\ElectionModel;
use App\Models\StudentVoteModel;
use App\Models\TeacherVoteModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Stage 1: migration, seeder, dan constraint database yang menjaga
 * integritas suara (1 suara aktif, riwayat unlock, FK, CHECK, strict mode).
 *
 * @internal
 */
final class SchemaIntegrityTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';
    protected $seed      = DatabaseSeeder::class;

    private function vote(string $table, string $voterColumn, int $voterId, int $candidateId): int
    {
        $this->db->table($table)->insert([
            'election_id'  => 1,
            $voterColumn   => $voterId,
            'candidate_id' => $candidateId,
            'voted_at'     => Time::now()->toDateTimeString(),
        ]);

        return (int) $this->db->insertID();
    }

    public function testSeederCreatesDevelopmentData(): void
    {
        $this->seeInDatabase('admins', ['username' => 'admin']);
        $this->assertSame(3, $this->db->table('candidates')->countAllResults());
        $this->assertSame(1, $this->db->table('elections')->countAllResults());
        $this->seeInDatabase('students', ['nisn' => '0000000001']);
        $this->seeInDatabase('teachers', ['nip' => '000000000000000001']);
    }

    public function testSeededElectionIsOngoing(): void
    {
        $this->assertSame('ONGOING', model(ElectionModel::class)->getCurrentElection()['status']);
    }

    public function testElectionStatusColumnIsSyncedWithSchedule(): void
    {
        $this->db->table('elections')->where('id', 1)->update([
            'start_at' => '2020-01-01 07:00:00',
            'end_at'   => '2020-01-01 12:00:00',
            'status'   => 'ONGOING',
        ]);

        $this->assertSame('FINISHED', model(ElectionModel::class)->getCurrentElection()['status']);
        $this->seeInDatabase('elections', ['id' => 1, 'status' => 'FINISHED']);
    }

    public function testStudentCanHaveOnlyOneActiveVote(): void
    {
        $this->vote('student_votes', 'student_id', 1, 1);

        $this->expectException(DatabaseException::class);
        $this->vote('student_votes', 'student_id', 1, 2);
    }

    public function testTeacherCanHaveOnlyOneActiveVote(): void
    {
        $this->vote('teacher_votes', 'teacher_id', 1, 1);

        $this->expectException(DatabaseException::class);
        $this->vote('teacher_votes', 'teacher_id', 1, 3);
    }

    public function testUnlockedVoteStaysAsHistoryAndAllowsNewVote(): void
    {
        $firstId = $this->vote('student_votes', 'student_id', 1, 1);

        $this->db->table('student_votes')->where('id', $firstId)->update([
            'status'      => StudentVoteModel::STATUS_UNLOCKED,
            'unlocked_at' => Time::now()->toDateTimeString(),
        ]);
        $this->db->table('vote_unlock_logs')->insert([
            'election_id'     => 1,
            'student_id'      => 1,
            'student_vote_id' => $firstId,
            'admin_id'        => 1,
            'reason'          => 'Salah pilih (uji)',
            'unlocked_at'     => Time::now()->toDateTimeString(),
        ]);

        $model = model(StudentVoteModel::class);
        $this->assertFalse($model->hasVoted(1, 1));

        $this->vote('student_votes', 'student_id', 1, 2);

        $this->assertSame(2, (int) $model->findActiveVote(1, 1)['candidate_id']);
        $this->assertSame(2, $this->db->table('student_votes')->where('student_id', 1)->countAllResults());
        $this->seeInDatabase('student_votes', ['id' => $firstId, 'status' => 'UNLOCKED', 'candidate_id' => 1]);
    }

    public function testActiveVoteLookupsAreSeparatedByVoterType(): void
    {
        $this->vote('student_votes', 'student_id', 1, 1);

        $this->assertTrue(model(StudentVoteModel::class)->hasVoted(1, 1));
        $this->assertFalse(model(TeacherVoteModel::class)->hasVoted(1, 1));
    }

    public function testUnlockLogMustReferenceExactlyOneVoterType(): void
    {
        $studentVote = $this->vote('student_votes', 'student_id', 1, 1);
        $teacherVote = $this->vote('teacher_votes', 'teacher_id', 1, 1);

        $this->expectException(DatabaseException::class);
        $this->db->table('vote_unlock_logs')->insert([
            'election_id'     => 1,
            'student_id'      => 1,
            'teacher_id'      => 1,
            'student_vote_id' => $studentVote,
            'teacher_vote_id' => $teacherVote,
            'admin_id'        => 1,
            'reason'          => 'Tidak valid',
            'unlocked_at'     => Time::now()->toDateTimeString(),
        ]);
    }

    public function testVoterWithVoteCannotBeDeleted(): void
    {
        $this->vote('student_votes', 'student_id', 1, 1);

        $this->expectException(DatabaseException::class);
        $this->db->table('students')->where('id', 1)->delete();
    }

    public function testCandidateWithVoteCannotBeDeleted(): void
    {
        $this->vote('teacher_votes', 'teacher_id', 1, 2);

        $this->expectException(DatabaseException::class);
        $this->db->table('candidates')->where('id', 2)->delete();
    }

    public function testElectionEndMustBeAfterStart(): void
    {
        $this->expectException(DatabaseException::class);
        $this->db->table('elections')->where('id', 1)->update(['end_at' => '2000-01-01 00:00:00']);
    }

    public function testStrictModeRejectsInvalidEnumInsteadOfTruncating(): void
    {
        $this->expectException(DatabaseException::class);
        $this->db->table('students')->insert([
            'nisn'          => '0000009999',
            'name'          => 'Uji Strict',
            'jenis_kelamin' => 'X',
            'kelas'         => '7A',
            'kodeunik'      => '01012013',
        ]);
    }

    public function testCandidateThemeAssetIsStoredAsJson(): void
    {
        $model = model(CandidateModel::class);
        $model->update(1, ['theme_asset' => ['banner' => 'banner-01.webp']]);

        $this->assertSame(['banner' => 'banner-01.webp'], $model->find(1)['theme_asset']);
        $this->assertNull($model->find(2)['theme_asset']);
        $this->assertStringEndsWith('uploads/candidates/banner-01.webp', CandidateModel::assetUrl('banner-01.webp'));
        $this->assertStringEndsWith('uploads/candidates/evil.php', CandidateModel::assetUrl('../../evil.php'));
    }

    public function testIdentifiersKeepLeadingZero(): void
    {
        $row = $this->db->table('students')->where('nisn', '0000000003')->get()->getRowArray();

        $this->assertSame('0000000003', $row['nisn']);
        $this->assertSame('01032013', $row['kodeunik']);
    }
}
