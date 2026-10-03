<?php

namespace Config;

use App\Libraries\CandidateAssets;
use App\Libraries\VoterAvatars;
use App\Services\AnalyticsService;
use App\Services\Import\ImportStore;
use App\Services\OnlineVisitors;
use App\Services\ParticipationWall;
use App\Services\UnlockService;
use App\Services\VisitorTracker;
use App\Services\VoterDirectory;
use App\Services\VoterEditor;
use App\Services\VoteService;
use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */

    /**
     * Logika voting siswa/guru (Stage 2). Dipanggil lewat service('voting').
     * Test dapat menggantinya dengan Services::injectMock('voting', ...).
     */
    public static function voting(bool $getShared = true): VoteService
    {
        if ($getShared) {
            return static::getSharedInstance('voting');
        }

        return new VoteService();
    }

    /**
     * Analitik & live count admin (Stage 3). service('analytics')
     */
    public static function analytics(bool $getShared = true): AnalyticsService
    {
        if ($getShared) {
            return static::getSharedInstance('analytics');
        }

        return new AnalyticsService();
    }

    /**
     * Unlock hak suara oleh admin (Stage 3). service('unlock')
     */
    public static function unlock(bool $getShared = true): UnlockService
    {
        if ($getShared) {
            return static::getSharedInstance('unlock');
        }

        return new UnlockService();
    }

    /**
     * Daftar & pencarian pemilih di panel admin (Stage 3). service('voterDirectory')
     */
    public static function voterDirectory(bool $getShared = true): VoterDirectory
    {
        if ($getShared) {
            return static::getSharedInstance('voterDirectory');
        }

        return new VoterDirectory();
    }

    /**
     * Tambah & ubah siswa/guru dari panel admin (Stage 11). service('voterEditor')
     */
    public static function voterEditor(bool $getShared = true): VoterEditor
    {
        if ($getShared) {
            return static::getSharedInstance('voterEditor');
        }

        return new VoterEditor();
    }

    /**
     * Unggahan foto/asset kandidat ke public/uploads/candidates (Stage 3).
     * Test menggantinya dengan folder sementara lewat Services::injectMock().
     */
    public static function candidateAssets(bool $getShared = true): CandidateAssets
    {
        if ($getShared) {
            return static::getSharedInstance('candidateAssets');
        }

        return new CandidateAssets();
    }

    /**
     * Penyimpanan pratinjau import di writable/uploads/imports (Stage 3).
     */
    public static function importStore(bool $getShared = true): ImportStore
    {
        if ($getShared) {
            return static::getSharedInstance('importStore');
        }

        return new ImportStore();
    }

    /**
     * Pencatat pengunjung online (Stage 19): halaman dibuka & detak presence.js.
     */
    public static function visitorTracker(bool $getShared = true): VisitorTracker
    {
        if ($getShared) {
            return static::getSharedInstance('visitorTracker');
        }

        return new VisitorTracker();
    }

    /**
     * Angka & daftar pengunjung online (Stage 19).
     */
    public static function onlineVisitors(bool $getShared = true): OnlineVisitors
    {
        if ($getShared) {
            return static::getSharedInstance('onlineVisitors');
        }

        return new OnlineVisitors();
    }

    /**
     * Foto & avatar DiceBear pemilih ke public/uploads/avatars (Stage 26).
     * Test menggantinya dengan folder sementara lewat Services::injectMock().
     */
    public static function voterAvatars(bool $getShared = true): VoterAvatars
    {
        if ($getShared) {
            return static::getSharedInstance('voterAvatars');
        }

        return new VoterAvatars();
    }

    /**
     * Data Living Election Wall: partisipasi per rombel & guru (Stage 26).
     */
    public static function participationWall(bool $getShared = true): ParticipationWall
    {
        if ($getShared) {
            return static::getSharedInstance('participationWall');
        }

        return new ParticipationWall();
    }
}
