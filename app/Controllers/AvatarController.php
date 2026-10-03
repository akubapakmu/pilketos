<?php

namespace App\Controllers;

use App\Libraries\AvatarException;
use App\Libraries\AvatarIdentity;
use App\Services\VoterType;
use CodeIgniter\HTTP\Exceptions\HTTPException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Avatar pemilih di dasbor (Stage 26, Living Election Wall).
 *
 * Student\AvatarController dan Teacher\AvatarController hanya menetapkan
 * voterType(); route berada di grup filter studentauth / teacherauth.
 * Identitas pemilih selalu dari sesi, tidak pernah dari input (tanpa IDOR).
 *
 * - upload : POST <siswa|guru>/avatar        (multipart "avatar")
 * - random : POST <siswa|guru>/avatar/acak   (style + seed; kosong = diacak server)
 * - remove : POST <siswa|guru>/avatar/hapus  (kembali ke avatar bawaan)
 *
 * Balasan JSON untuk fetch (dashboard-avatar.js), redirect + pesan untuk
 * formulir biasa (tanpa JavaScript). Dibatasi 10 perubahan per 10 menit per
 * pemilih. Tidak dikunci status pemilihan (tidak memengaruhi hasil).
 */
abstract class AvatarController extends BaseController
{
    abstract protected function voterType(): VoterType;

    protected function voterId(): int
    {
        return (int) session()->get($this->voterType()->voterKey());
    }

    public function upload()
    {
        $file = $this->request->getFile('avatar');

        return $this->change(
            static fn ($avatars, VoterType $type, int $id): array => $file === null
                ? throw new AvatarException('Pilih foto lebih dulu.')
                : $avatars->upload($type, $id, $file),
            'Foto avatar disimpan.',
        );
    }

    public function random()
    {
        $style = $this->input('style');
        $seed  = $this->input('seed');

        // Formulir tanpa JavaScript tidak membawa pilihan: server yang mengacak.
        if (($style === null || $style === '') && ($seed === null || $seed === '')) {
            ['style' => $style, 'seed' => $seed] = AvatarIdentity::random();
        }

        return $this->change(
            static fn ($avatars, VoterType $type, int $id): array => $avatars->choose($type, $id, $style, $seed),
            'Avatar baru dipakai.',
        );
    }

    public function remove()
    {
        return $this->change(
            static fn ($avatars, VoterType $type, int $id): array => $avatars->reset($type, $id),
            'Foto dihapus. Avatar bawaan dipakai lagi.',
        );
    }

    /**
     * @param callable(\App\Libraries\VoterAvatars, VoterType, int): array $action
     */
    private function change(callable $action, string $success): RedirectResponse|ResponseInterface
    {
        $type    = $this->voterType();
        $id      = $this->voterId();
        $avatars = service('voterAvatars');
        $wait    = $avatars->throttle($type, $id);

        if ($wait > 0) {
            return $this->respond(429, sprintf('Avatar sudah terlalu sering diubah. Coba lagi dalam %s.', durasi($wait)), null, $wait);
        }

        try {
            $avatar = $action($avatars, $type, $id);
        } catch (AvatarException $e) {
            return $this->respond(422, $e->getMessage());
        }

        return $this->respond(200, $success, $avatar);
    }

    private function respond(int $status, string $message, ?array $avatar = null, int $retryAfter = 0): RedirectResponse|ResponseInterface
    {
        if ($this->wantsJson()) {
            $response = $this->response->setStatusCode($status)->setJSON(array_filter([
                'status'  => $status === 200 ? 'ok' : ($status === 429 ? 'throttled' : 'error'),
                'message' => $message,
                'avatar'  => $avatar,
            ], static fn ($value): bool => $value !== null));

            return $retryAfter > 0 ? $response->setHeader('Retry-After', (string) $retryAfter) : $response;
        }

        return redirect()->to($this->voterType()->path() . '#avatar')->with($status === 200 ? 'success' : 'error', $message);
    }

    /**
     * Nilai dari body JSON (fetch) atau form POST. Selain string = null.
     */
    private function input(string $key): ?string
    {
        if ($this->request->is('json')) {
            try {
                $value = $this->request->getJsonVar($key);
            } catch (HTTPException) {
                $value = null;
            }
        } else {
            $value = $this->request->getPost($key);
        }

        return is_string($value) ? trim($value) : null;
    }

    private function wantsJson(): bool
    {
        return $this->request->isAJAX()
            || $this->request->is('json')
            || str_contains(strtolower($this->request->getHeaderLine('Accept')), 'application/json');
    }
}
