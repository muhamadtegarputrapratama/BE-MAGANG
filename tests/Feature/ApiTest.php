<?php

use App\Enums\StatusPengajuan;
use App\Models\PengajuanMagang;
use App\Models\User;
use App\Services\SuratMagangService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Helper data untuk seluruh skenario API
|--------------------------------------------------------------------------
| Semua test berjalan dengan RefreshDatabase dari tests/Pest.php.
| phpunit.xml mengarahkan koneksi test ke SQLite :memory:.
*/

function apiUser(string $role = 'mahasiswa', array $attributes = []): User
{
    $spatieRole = Role::findOrCreate($role, 'web');

    $defaults = [
        'name' => $role === 'admin_kpap' ? 'Admin KPAP' : 'Mahasiswa Test',
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('password123'),
        'nim' => $role === 'admin_kpap' ? null : 'E1412300001',
        'departemen' => $role === 'admin_kpap' ? null : 'MNH',
        'no_wa' => $role === 'admin_kpap' ? null : '081234567890',
        'role' => $role,
    ];

    $user = User::factory()->create(array_merge($defaults, $attributes));
    $user->assignRole($spatieRole);

    return $user;
}

function apiPengajuan(User $user, array $attributes = []): PengajuanMagang
{
    $defaults = [
        'user_id' => $user->id,
        'nama' => $user->name,
        'anggota' => [[
            'user_id' => null,
            'nama' => $user->name,
            'nim' => $user->nim,
            'departemen' => $user->departemen,
        ]],
        'instansi' => 'PT Contoh Indonesia',
        'alamat_instansi' => 'Jl. Merdeka No. 1, Bogor',
        'tanggal_mulai' => now()->addDays(10)->toDateString(),
        'tanggal_selesai' => now()->addDays(40)->toDateString(),
        'instansi_kedua' => null,
        'alamat_instansi_kedua' => null,
        'tanggal_mulai_kedua' => null,
        'tanggal_selesai_kedua' => null,
        'status' => StatusPengajuan::Draft->value,
        'kode_verifikasi' => Str::random(32),
        'ditandatangani_at' => null,
        'dikirim_at' => null,
        'diverifikasi_at' => null,
        'diverifikasi_oleh' => null,
        'catatan_revisi' => null,
        'nomor_surat' => null,
        'nomor_surat_kedua' => null,
        'file_surat_pengajuan' => null,
        'file_surat_pengajuan_kedua' => null,
        'file_surat_magang' => null,
        'file_surat_magang_kedua' => null,
        'file_bukti_spp' => null,
        'file_surat_rekomendasi' => null,
    ];

    // forceCreate: abaikan $fillable supaya user_id, status, kode_verifikasi, dll. ikut tersimpan.
    return PengajuanMagang::forceCreate(array_merge($defaults, $attributes));
}

function apiFormPengajuan(array $overrides = []): array
{
    return array_merge([
        'instansi' => 'pt contoh indonesia',
        'alamat_instansi' => 'jl. merdeka no. 1, bogor',
        'tanggal_mulai' => now()->addDays(10)->toDateString(),
        'tanggal_selesai' => now()->addDays(40)->toDateString(),
        'anggota' => [],
    ], $overrides);
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    Role::findOrCreate('mahasiswa', 'web');
    Role::findOrCreate('admin_kpap', 'web');
});

/*
|--------------------------------------------------------------------------
| 1. Registrasi dan autentikasi
|--------------------------------------------------------------------------
*/

it('register mahasiswa dengan data valid dan menetapkan role mahasiswa otomatis', function () {
    $response = $this->postJson('/api/register', [
        'name' => '  tegar pratama  ',
        'nim' => 'e1412300002',
        'email' => '  TEGAR.TEST@EXAMPLE.COM ',
        'no_wa' => '081234567891',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Registrasi berhasil. Silakan login.')
        ->assertJsonPath('user.name', 'Tegar Pratama')
        ->assertJsonPath('user.nim', 'E1412300002')
        ->assertJsonPath('user.email', 'tegar.test@example.com')
        ->assertJsonPath('user.no_wa', '081234567891')
        ->assertJsonPath('user.role', 'mahasiswa');

    $user = User::where('email', 'tegar.test@example.com')->firstOrFail();

    expect($user->departemen)->toBe('MNH')
        ->and($user->hasRole('mahasiswa'))->toBeTrue()
        ->and(Hash::check('password123', $user->password))->toBeTrue();
});

it('menolak registrasi jika nama wajib tidak diisi', function () {
    $this->postJson('/api/register', [
        'nim' => 'E1412300003',
        'email' => 'nama-kosong@example.com',
        'no_wa' => '081234567892',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertUnprocessable()->assertJsonValidationErrors(['name']);
});

it('menolak registrasi jika format NIM tidak sesuai prefix yang didukung', function () {
    $this->postJson('/api/register', [
        'name' => 'Mahasiswa Test',
        'nim' => 'E9992300001',
        'email' => 'nim-invalid@example.com',
        'no_wa' => '081234567893',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertUnprocessable()->assertJsonValidationErrors(['nim']);
});

it('memetakan prefix NIM E241 ke kode departemen HH sesuai implementasi saat ini', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Mahasiswa Hasil Hutan',
        'nim' => 'E2412300001',
        'email' => 'e241@example.com',
        'no_wa' => '081234567894',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated();

    expect(User::where('nim', 'E2412300001')->value('departemen'))->toBe('HH');
});

it('menolak registrasi jika NIM sudah digunakan', function () {
    apiUser('mahasiswa', ['nim' => 'E1412300010']);

    $this->postJson('/api/register', [
        'name' => 'Mahasiswa Duplikat',
        'nim' => 'E1412300010',
        'email' => 'duplikat-nim@example.com',
        'no_wa' => '081234567895',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertUnprocessable()->assertJsonValidationErrors(['nim']);
});

it('menolak registrasi jika email sudah digunakan', function () {
    apiUser('mahasiswa', ['email' => 'sudah-ada@example.com']);

    $this->postJson('/api/register', [
        'name' => 'Mahasiswa Duplikat',
        'nim' => 'E1412300011',
        'email' => 'sudah-ada@example.com',
        'no_wa' => '081234567896',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
});

it('menolak registrasi jika nomor WhatsApp sudah digunakan', function () {
    apiUser('mahasiswa', ['no_wa' => '081234567897']);

    $this->postJson('/api/register', [
        'name' => 'Mahasiswa Duplikat',
        'nim' => 'E1412300012',
        'email' => 'wa-duplikat@example.com',
        'no_wa' => '081234567897',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertUnprocessable()->assertJsonValidationErrors(['no_wa']);
});

it('menolak nomor WhatsApp yang bukan 10 sampai 15 digit angka', function () {
    $this->postJson('/api/register', [
        'name' => 'Nomor Tidak Valid',
        'nim' => 'E1412300013',
        'email' => 'wa-invalid@example.com',
        'no_wa' => '0812-ABC',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertUnprocessable()->assertJsonValidationErrors(['no_wa']);
});

it('menolak password kurang dari delapan karakter', function () {
    $this->postJson('/api/register', [
        'name' => 'Password Pendek',
        'nim' => 'E1412300014',
        'email' => 'password-pendek@example.com',
        'no_wa' => '081234567898',
        'password' => '123',
        'password_confirmation' => '123',
    ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
});

it('menolak konfirmasi password yang tidak sama', function () {
    $this->postJson('/api/register', [
        'name' => 'Konfirmasi Salah',
        'nim' => 'E1412300015',
        'email' => 'konfirmasi@example.com',
        'no_wa' => '081234567899',
        'password' => 'password123',
        'password_confirmation' => 'password-lain',
    ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
});

it('login dengan email dan password yang benar', function () {
    $user = apiUser('mahasiswa', [
        'email' => 'login@example.com',
        'password' => Hash::make('password123'),
    ]);

    $this->postJson('/api/login', [
        'email' => ' LOGIN@EXAMPLE.COM ',
        'password' => 'password123',
    ])->assertOk()
        ->assertJsonPath('message', 'Login berhasil.')
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonStructure(['token', 'user']);
});

it('menolak login dengan password yang salah', function () {
    apiUser('mahasiswa', [
        'email' => 'salah-password@example.com',
        'password' => Hash::make('password123'),
    ]);

    $this->postJson('/api/login', [
        'email' => 'salah-password@example.com',
        'password' => 'password-salah',
    ])->assertUnauthorized()
        ->assertJsonPath('message', 'Email atau password salah.');
});

it('menolak login jika email tidak terdaftar', function () {
    $this->postJson('/api/login', [
        'email' => 'tidak-ada@example.com',
        'password' => 'password123',
    ])->assertUnauthorized();
});

it('menolak akses endpoint terlindungi tanpa token', function () {
    $this->getJson('/api/me')->assertUnauthorized();
    $this->getJson('/api/pengajuan-magang')->assertUnauthorized();
    $this->getJson('/api/admin/pengajuan-magang')->assertUnauthorized();
});

it('menampilkan data akun pada endpoint me', function () {
    $user = apiUser();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('email', $user->email)
        ->assertJsonPath('role', 'mahasiswa');
});

it('logout menghapus token akses yang sedang digunakan', function () {
    $user = apiUser();
    $token = $user->createToken('test-token');

    $this->withToken($token->plainTextToken)
        ->postJson('/api/logout')
        ->assertOk()
        ->assertJsonPath('message', 'Logout berhasil.');

    expect($user->tokens()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| 2. Hak akses role
|--------------------------------------------------------------------------
*/

it('mahasiswa tidak dapat mengakses endpoint admin', function () {
    $mahasiswa = apiUser();

    $this->actingAs($mahasiswa, 'sanctum')
        ->getJson('/api/admin/pengajuan-magang')
        ->assertForbidden();
});

it('admin tidak dapat mengakses endpoint khusus mahasiswa', function () {
    $admin = apiUser('admin_kpap');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/pengajuan-magang')
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| 3. Pengajuan oleh mahasiswa
|--------------------------------------------------------------------------
*/

it('mahasiswa dapat membuat draft pengajuan satu instansi', function () {
    $user = apiUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/pengajuan-magang', apiFormPengajuan())
        ->assertCreated()
        ->assertJsonPath('message', 'Data pengajuan disimpan sebagai draft.')
        ->assertJsonPath('data.status.value', 'draft')
        ->assertJsonPath('data.instansi', 'PT Contoh Indonesia')
        ->assertJsonPath('data.jumlah_surat', 1);

    expect(PengajuanMagang::where('user_id', $user->id)->count())->toBe(1);
});

it('mahasiswa dapat membuat draft dengan dua instansi', function () {
    $user = apiUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/pengajuan-magang', apiFormPengajuan([
            'instansi_kedua' => 'cv tujuan kedua',
            'alamat_instansi_kedua' => 'jl. kedua no. 2',
            'tanggal_mulai_kedua' => now()->addDays(15)->toDateString(),
            'tanggal_selesai_kedua' => now()->addDays(45)->toDateString(),
        ]))
        ->assertCreated()
        ->assertJsonPath('data.instansi_kedua', 'CV Tujuan Kedua')
        ->assertJsonPath('data.jumlah_surat', 2);
});

it('menghapus entri ketua dari daftar anggota yang dikirim mahasiswa', function () {
    $user = apiUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/pengajuan-magang', apiFormPengajuan([
            'anggota' => [
                ['nama' => $user->name, 'nim' => $user->nim],
                ['nama' => 'Anggota Tambahan', 'nim' => 'E2412300002'],
            ],
        ]))
        ->assertCreated()
        ->assertJsonPath('data.jumlah_mahasiswa', 2)
        ->assertJsonCount(1, 'data.anggota');

    $pengajuan = PengajuanMagang::where('user_id', $user->id)->firstOrFail();

    expect($pengajuan->anggotaTambahan())->toHaveCount(1)
        ->and($pengajuan->anggotaTambahan()[0]['nim'])->toBe('E2412300002');
});

it('menolak tanggal mulai yang sudah lewat saat membuat pengajuan baru', function () {
    $user = apiUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/pengajuan-magang', apiFormPengajuan([
            'tanggal_mulai' => now()->subDay()->toDateString(),
            'tanggal_selesai' => now()->addDays(10)->toDateString(),
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tanggal_mulai']);
});

it('menolak tanggal selesai yang lebih awal dari tanggal mulai', function () {
    $user = apiUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/pengajuan-magang', apiFormPengajuan([
            'tanggal_mulai' => now()->addDays(20)->toDateString(),
            'tanggal_selesai' => now()->addDays(10)->toDateString(),
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tanggal_selesai']);
});

it('menolak tanggal selesai yang tidak setelah tanggal mulai', function () {
    $user = apiUser();
    $tanggal = now()->addDays(10)->toDateString();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/pengajuan-magang', apiFormPengajuan([
            'tanggal_mulai' => $tanggal,
            'tanggal_selesai' => $tanggal,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tanggal_selesai']);
});

it('menolak tanggal selesai instansi kedua yang lebih awal dari tanggal mulainya', function () {
    $user = apiUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/pengajuan-magang', apiFormPengajuan([
            'instansi_kedua' => 'CV Kedua',
            'alamat_instansi_kedua' => 'Jl. Kedua',
            'tanggal_mulai_kedua' => now()->addDays(60)->toDateString(),
            'tanggal_selesai_kedua' => now()->addDays(50)->toDateString(),
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tanggal_selesai_kedua']);
});

it('menolak instansi kedua yang sama dengan instansi pertama', function () {
    $user = apiUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/pengajuan-magang', apiFormPengajuan([
            'instansi' => 'PT Sama',
            'instansi_kedua' => 'PT Sama',
            'alamat_instansi_kedua' => 'Alamat kedua',
            'tanggal_mulai_kedua' => now()->addDays(15)->toDateString(),
            'tanggal_selesai_kedua' => now()->addDays(45)->toDateString(),
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['instansi_kedua']);
});

it('menolak instansi kedua tanpa alamat dan periode lengkap', function () {
    $user = apiUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/pengajuan-magang', apiFormPengajuan([
            'instansi_kedua' => 'PT Tujuan Kedua',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'alamat_instansi_kedua',
            'tanggal_mulai_kedua',
            'tanggal_selesai_kedua',
        ]);
});

it('menolak NIM anggota yang formatnya tidak valid', function () {
    $user = apiUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/pengajuan-magang', apiFormPengajuan([
            'anggota' => [
                ['nama' => 'Anggota Salah', 'nim' => '12345'],
            ],
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['anggota.0.nim']);
});

it('menolak NIM anggota yang duplikat', function () {
    $user = apiUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/pengajuan-magang', apiFormPengajuan([
            'anggota' => [
                ['nama' => 'Anggota Satu', 'nim' => 'E2412300002'],
                ['nama' => 'Anggota Dua', 'nim' => 'E2412300002'],
            ],
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['anggota.1.nim']);
});

it('menolak pengajuan baru jika mahasiswa masih memiliki pengajuan aktif', function () {
    $user = apiUser();
    apiPengajuan($user, ['status' => StatusPengajuan::Draft->value]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/pengajuan-magang', apiFormPengajuan())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['pengajuan']);

    expect(PengajuanMagang::where('user_id', $user->id)->count())->toBe(1);
});

it('mengizinkan pengajuan baru setelah pengajuan sebelumnya berstatus setuju', function () {
    $user = apiUser();
    apiPengajuan($user, ['status' => StatusPengajuan::Setuju->value]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/pengajuan-magang', apiFormPengajuan())
        ->assertCreated();

    expect(PengajuanMagang::where('user_id', $user->id)->count())->toBe(2);
});

it('mahasiswa hanya melihat daftar pengajuannya sendiri', function () {
    $pemilik = apiUser();
    $orangLain = apiUser('mahasiswa', [
        'nim' => 'E1412300002',
        'email' => 'oranglain@example.com',
        'no_wa' => '081234567892',
    ]);

    apiPengajuan($pemilik);
    apiPengajuan($orangLain);

    $response = $this->actingAs($pemilik, 'sanctum')
        ->getJson('/api/pengajuan-magang')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});

it('mahasiswa dapat melihat detail pengajuannya sendiri', function () {
    $user = apiUser();
    $pengajuan = apiPengajuan($user);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/pengajuan-magang/{$pengajuan->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $pengajuan->id);
});

it('mahasiswa tidak dapat melihat pengajuan milik orang lain', function () {
    $pemilik = apiUser();
    $orangLain = apiUser('mahasiswa', [
        'nim' => 'E1412300003',
        'email' => 'pemilik-lain@example.com',
        'no_wa' => '081234567893',
    ]);
    $pengajuan = apiPengajuan($orangLain);

    $this->actingAs($pemilik, 'sanctum')
        ->getJson("/api/pengajuan-magang/{$pengajuan->id}")
        ->assertForbidden();
});

it('mahasiswa dapat memperbarui draft dan perubahan membatalkan tanda tangan lama', function () {
    $user = apiUser();
    Storage::disk('local')->put('pengajuan/lama/surat.pdf', '%PDF-lama');

    $pengajuan = apiPengajuan($user, [
        'ditandatangani_at' => now(),
        'file_surat_pengajuan' => 'pengajuan/lama/surat.pdf',
    ]);

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/pengajuan-magang/{$pengajuan->id}", apiFormPengajuan([
            'instansi' => 'cv perubahan nama',
        ]))
        ->assertOk()
        ->assertJsonPath('message', 'Data pengajuan diperbarui. Silakan tanda tangan ulang.')
        ->assertJsonPath('data.instansi', 'CV Perubahan Nama')
        ->assertJsonPath('data.sudah_ditandatangani', false);

    expect($pengajuan->refresh()->ditandatangani_at)->toBeNull()
        ->and($pengajuan->file_surat_pengajuan)->toBeNull()
        ->and(Storage::disk('local')->exists('pengajuan/lama/surat.pdf'))->toBeFalse();
});

it('mahasiswa tidak dapat memperbarui pengajuan yang bukan miliknya', function () {
    $pemilik = apiUser();
    $orangLain = apiUser('mahasiswa', [
        'nim' => 'E1412300004',
        'email' => 'pemilik-update@example.com',
        'no_wa' => '081234567894',
    ]);
    $pengajuan = apiPengajuan($orangLain);

    $this->actingAs($pemilik, 'sanctum')
        ->putJson("/api/pengajuan-magang/{$pengajuan->id}", apiFormPengajuan())
        ->assertForbidden();
});

it('menolak pembaruan oleh mahasiswa jika status sudah menunggu verifikasi', function () {
    $user = apiUser();
    $pengajuan = apiPengajuan($user, [
        'status' => StatusPengajuan::MenungguVerifikasi->value,
    ]);

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/pengajuan-magang/{$pengajuan->id}", apiFormPengajuan())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

/*
|--------------------------------------------------------------------------
| 4. Preview, tanda tangan, dan pengiriman berkas
|--------------------------------------------------------------------------
*/

it('menghasilkan preview PDF untuk pemilik pengajuan', function () {
    $user = apiUser();
    $pengajuan = apiPengajuan($user);

    $this->actingAs($user, 'sanctum')
        ->get("/api/pengajuan-magang/{$pengajuan->id}/preview-surat")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('menolak preview surat untuk instansi kedua jika instansi kedua tidak ada', function () {
    $user = apiUser();
    $pengajuan = apiPengajuan($user);

    $this->actingAs($user, 'sanctum')
        ->get("/api/pengajuan-magang/{$pengajuan->id}/preview-surat?urutan=2")
        ->assertNotFound();
});

it('mahasiswa dapat menandatangani surat pengajuan', function () {
    $user = apiUser();
    $pengajuan = apiPengajuan($user);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/pengajuan-magang/{$pengajuan->id}/tanda-tangan")
        ->assertOk()
        ->assertJsonPath('data.sudah_ditandatangani', true);

    $pengajuan->refresh();

    expect($pengajuan->ditandatangani_at)->not->toBeNull()
        ->and($pengajuan->file_surat_pengajuan)->not->toBeNull()
        ->and(Storage::disk('local')->exists($pengajuan->file_surat_pengajuan))->toBeTrue();
});

it('membuat dua PDF saat mahasiswa menandatangani pengajuan dua instansi', function () {
    $user = apiUser();
    $pengajuan = apiPengajuan($user, [
        'instansi_kedua' => 'CV Tujuan Kedua',
        'alamat_instansi_kedua' => 'Jl. Kedua No. 2',
        'tanggal_mulai_kedua' => now()->addDays(15)->toDateString(),
        'tanggal_selesai_kedua' => now()->addDays(45)->toDateString(),
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/pengajuan-magang/{$pengajuan->id}/tanda-tangan")
        ->assertOk();

    $pengajuan->refresh();

    expect($pengajuan->file_surat_pengajuan)->not->toBeNull()
        ->and($pengajuan->file_surat_pengajuan_kedua)->not->toBeNull()
        ->and(Storage::disk('local')->exists($pengajuan->file_surat_pengajuan))->toBeTrue()
        ->and(Storage::disk('local')->exists($pengajuan->file_surat_pengajuan_kedua))->toBeTrue();
});

it('tanda tangan ulang tidak membuat file baru jika surat sudah ditandatangani', function () {
    $user = apiUser();
    $pengajuan = apiPengajuan($user);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/pengajuan-magang/{$pengajuan->id}/tanda-tangan")
        ->assertOk();

    $pengajuan->refresh();
    $pathPertama = $pengajuan->file_surat_pengajuan;

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/pengajuan-magang/{$pengajuan->id}/tanda-tangan")
        ->assertOk();

    expect($pengajuan->refresh()->file_surat_pengajuan)->toBe($pathPertama);
});

it('mahasiswa dapat membatalkan tanda tangan dan menghapus PDF final', function () {
    $user = apiUser();
    $pengajuan = apiPengajuan($user);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/pengajuan-magang/{$pengajuan->id}/tanda-tangan")
        ->assertOk();

    $pengajuan->refresh();
    $path = $pengajuan->file_surat_pengajuan;

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/pengajuan-magang/{$pengajuan->id}/tanda-tangan")
        ->assertOk()
        ->assertJsonPath('data.sudah_ditandatangani', false);

    expect($pengajuan->refresh()->ditandatangani_at)->toBeNull()
        ->and($pengajuan->file_surat_pengajuan)->toBeNull()
        ->and(Storage::disk('local')->exists($path))->toBeFalse();
});

it('menolak pengiriman pengajuan sebelum surat ditandatangani', function () {
    $user = apiUser();
    $pengajuan = apiPengajuan($user);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/pengajuan-magang/{$pengajuan->id}/kirim", [
            'bukti_spp' => UploadedFile::fake()->create('spp.pdf', 100, 'application/pdf'),
            'surat_rekomendasi' => UploadedFile::fake()->create('rekomendasi.pdf', 100, 'application/pdf'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tanda_tangan']);
});

it('menolak pengiriman jika bukti SPP dan surat rekomendasi belum tersedia', function () {
    $user = apiUser();
    $pengajuan = apiPengajuan($user, [
        'ditandatangani_at' => now(),
        'file_surat_pengajuan' => 'pengajuan/final.pdf',
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/pengajuan-magang/{$pengajuan->id}/kirim", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['bukti_spp', 'surat_rekomendasi']);
});

it('mahasiswa dapat mengirim pengajuan dengan dua berkas yang valid', function () {
    $user = apiUser();
    $pengajuan = apiPengajuan($user, [
        'ditandatangani_at' => now(),
        'file_surat_pengajuan' => 'pengajuan/final.pdf',
    ]);
    Storage::disk('local')->put('pengajuan/final.pdf', '%PDF-final');

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/pengajuan-magang/{$pengajuan->id}/kirim", [
            'bukti_spp' => UploadedFile::fake()->create('bukti-spp.pdf', 100, 'application/pdf'),
            'surat_rekomendasi' => UploadedFile::fake()->create('surat-rekomendasi.pdf', 100, 'application/pdf'),
        ])
        ->assertOk()
        ->assertJsonPath('data.status.value', 'menunggu_verifikasi');

    $pengajuan->refresh();

    expect($pengajuan->status)->toBe(StatusPengajuan::MenungguVerifikasi)
        ->and($pengajuan->file_bukti_spp)->not->toBeNull()
        ->and($pengajuan->file_surat_rekomendasi)->not->toBeNull()
        ->and($pengajuan->dikirim_at)->not->toBeNull()
        ->and(Storage::disk('local')->exists($pengajuan->file_bukti_spp))->toBeTrue()
        ->and(Storage::disk('local')->exists($pengajuan->file_surat_rekomendasi))->toBeTrue();
});

it('menolak format file upload selain PDF JPG JPEG dan PNG', function () {
    $user = apiUser();
    $pengajuan = apiPengajuan($user, [
        'ditandatangani_at' => now(),
        'file_surat_pengajuan' => 'pengajuan/final.pdf',
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/pengajuan-magang/{$pengajuan->id}/kirim", [
            'bukti_spp' => UploadedFile::fake()->create('bukti.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'surat_rekomendasi' => UploadedFile::fake()->create('rekomendasi.pdf', 100, 'application/pdf'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['bukti_spp']);
});

it('mahasiswa tidak dapat mengirim pengajuan milik orang lain', function () {
    $mahasiswa = apiUser();
    $pemilik = apiUser('mahasiswa', [
        'nim' => 'E1412300005',
        'email' => 'pemilik-kirim@example.com',
        'no_wa' => '081234567895',
    ]);
    $pengajuan = apiPengajuan($pemilik, [
        'ditandatangani_at' => now(),
        'file_surat_pengajuan' => 'pengajuan/final.pdf',
    ]);

    $this->actingAs($mahasiswa, 'sanctum')
        ->postJson("/api/pengajuan-magang/{$pengajuan->id}/kirim", [])
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| 5. Fitur admin KPAP
|--------------------------------------------------------------------------
*/

it('admin melihat daftar pengajuan terkirim tetapi bukan draft', function () {
    $admin = apiUser('admin_kpap');
    $mahasiswa = apiUser();
    apiPengajuan($mahasiswa, ['status' => StatusPengajuan::Draft->value]);
    apiPengajuan($mahasiswa, [
        'status' => StatusPengajuan::MenungguVerifikasi->value,
        'kode_verifikasi' => Str::random(32),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/pengajuan-magang')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});

it('admin dapat memfilter daftar pengajuan berdasarkan status', function () {
    $admin = apiUser('admin_kpap');
    $mahasiswa = apiUser();

    apiPengajuan($mahasiswa, [
        'status' => StatusPengajuan::MenungguVerifikasi->value,
    ]);
    apiPengajuan($mahasiswa, [
        'status' => StatusPengajuan::Revisi->value,
        'kode_verifikasi' => Str::random(32),
    ]);

    $response = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/pengajuan-magang?status=revisi')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.status.value'))->toBe('revisi');
});

it('menolak filter status admin yang tidak dikenal', function () {
    $admin = apiUser('admin_kpap');

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/pengajuan-magang?status=ditolak')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('admin dapat melihat ringkasan jumlah pengajuan per status', function () {
    $admin = apiUser('admin_kpap');
    $mahasiswa = apiUser();

    apiPengajuan($mahasiswa, ['status' => StatusPengajuan::Draft->value]);
    apiPengajuan($mahasiswa, ['status' => StatusPengajuan::MenungguVerifikasi->value]);
    apiPengajuan($mahasiswa, [
        'status' => StatusPengajuan::Revisi->value,
        'kode_verifikasi' => Str::random(32),
    ]);
    apiPengajuan($mahasiswa, [
        'status' => StatusPengajuan::Setuju->value,
        'kode_verifikasi' => Str::random(32),
    ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/pengajuan-magang/ringkasan')
        ->assertOk()
        ->assertJsonPath('data.menunggu_verifikasi', 1)
        ->assertJsonPath('data.revisi', 1)
        ->assertJsonPath('data.setuju', 1);
});

it('admin dapat melihat detail pengajuan yang sudah dikirim', function () {
    $admin = apiUser('admin_kpap');
    $mahasiswa = apiUser();
    $pengajuan = apiPengajuan($mahasiswa, [
        'status' => StatusPengajuan::MenungguVerifikasi->value,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/admin/pengajuan-magang/{$pengajuan->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $pengajuan->id)
        ->assertJsonPath('data.mahasiswa.nim', $mahasiswa->nim);
});

it('admin tidak dapat membuka detail pengajuan yang masih draft', function () {
    $admin = apiUser('admin_kpap');
    $mahasiswa = apiUser();
    $pengajuan = apiPengajuan($mahasiswa);

    $this->actingAs($admin, 'sanctum')
        ->getJson("/api/admin/pengajuan-magang/{$pengajuan->id}")
        ->assertNotFound();
});

it('admin dapat mengubah data mahasiswa dan data instansi pada pengajuan yang menunggu', function () {
    $admin = apiUser('admin_kpap');
    $mahasiswa = apiUser();
    $pengajuan = apiPengajuan($mahasiswa, [
        'status' => StatusPengajuan::MenungguVerifikasi->value,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/pengajuan-magang/{$pengajuan->id}", [
            'nama' => 'nama mahasiswa baru',
            'nim' => 'E2412300009',
            'instansi' => 'cv instansi baru',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Data pengajuan magang berhasil diperbarui.');

    expect($mahasiswa->refresh()->name)->toBe('Nama Mahasiswa Baru')
        ->and($mahasiswa->nim)->toBe('E2412300009')
        ->and($mahasiswa->departemen)->toBe('HH')
        ->and($pengajuan->refresh()->instansi)->toBe('CV Instansi Baru');
});

it('admin tidak dapat mengedit pengajuan yang sudah disetujui', function () {
    $admin = apiUser('admin_kpap');
    $mahasiswa = apiUser();
    $pengajuan = apiPengajuan($mahasiswa, [
        'status' => StatusPengajuan::Setuju->value,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/pengajuan-magang/{$pengajuan->id}", [
            'instansi' => 'Instansi Diubah',
        ])
        ->assertForbidden();
});

it('admin tidak dapat mengedit draft melalui endpoint admin', function () {
    $admin = apiUser('admin_kpap');
    $mahasiswa = apiUser();
    $pengajuan = apiPengajuan($mahasiswa);

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/pengajuan-magang/{$pengajuan->id}", [
            'instansi' => 'Instansi Diubah',
        ])
        ->assertNotFound();
});

it('admin menolak perubahan NIM yang formatnya tidak valid', function () {
    $admin = apiUser('admin_kpap');
    $mahasiswa = apiUser();
    $pengajuan = apiPengajuan($mahasiswa, [
        'status' => StatusPengajuan::MenungguVerifikasi->value,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/pengajuan-magang/{$pengajuan->id}", [
            'nim' => 'NIM-SALAH',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['nim']);
});

it('admin harus mengisi catatan ketika mengembalikan pengajuan untuk revisi', function () {
    $admin = apiUser('admin_kpap');
    $mahasiswa = apiUser();
    $pengajuan = apiPengajuan($mahasiswa, [
        'status' => StatusPengajuan::MenungguVerifikasi->value,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/pengajuan-magang/{$pengajuan->id}/status", [
            'status' => 'revisi',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['catatan']);
});

it('admin dapat meminta revisi dan menyimpan catatan serta identitas pemeriksa', function () {
    $admin = apiUser('admin_kpap');
    $mahasiswa = apiUser();
    $pengajuan = apiPengajuan($mahasiswa, [
        'status' => StatusPengajuan::MenungguVerifikasi->value,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/pengajuan-magang/{$pengajuan->id}/status", [
            'status' => 'revisi',
            'catatan' => 'Mohon perbaiki alamat instansi.',
        ])
        ->assertOk()
        ->dump()
        ->assertJsonPath('data.status', 'revisi')
        ->assertJsonPath('data.status', 'revisi')
        ->assertJsonPath('data.catatan_revisi', 'Mohon perbaiki alamat instansi.');

    expect($pengajuan->refresh()->diverifikasi_oleh)->toBe($admin->id)
        ->and($pengajuan->diverifikasi_at)->not->toBeNull();
});

it('admin tidak dapat memproses pengajuan yang bukan berstatus menunggu verifikasi', function () {
    $admin = apiUser('admin_kpap');
    $mahasiswa = apiUser();
    $pengajuan = apiPengajuan($mahasiswa, [
        'status' => StatusPengajuan::Revisi->value,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/pengajuan-magang/{$pengajuan->id}/status", [
            'status' => 'setuju',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('admin dapat menyetujui pengajuan satu instansi dan menerbitkan nomor serta PDF surat', function () {
    $admin = apiUser('admin_kpap');
    $mahasiswa = apiUser();
    $pengajuan = apiPengajuan($mahasiswa, [
        'status' => StatusPengajuan::MenungguVerifikasi->value,
        'dikirim_at' => now(),
    ]);

    $suratMagang = Mockery::mock(SuratMagangService::class);
    $suratMagang->shouldReceive('simpan')
        ->once()
        ->andReturnUsing(function ($model, $urutan) {
            $path = "pengajuan/{$model->id}/surat-magang.pdf";
            Storage::disk('local')->put($path, '%PDF-surat-magang');

            return $path;
        });
    app()->instance(SuratMagangService::class, $suratMagang);

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/pengajuan-magang/{$pengajuan->id}/status", [
            'status' => 'setuju',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'setuju');

    $pengajuan->refresh();

    expect($pengajuan->nomor_surat)->not->toBeNull()
        ->and($pengajuan->file_surat_magang)->toBe('pengajuan/'.$pengajuan->id.'/surat-magang.pdf')
        ->and(Storage::disk('local')->exists($pengajuan->file_surat_magang))->toBeTrue()
        ->and($pengajuan->diverifikasi_oleh)->toBe($admin->id);
});

it('admin menerbitkan dua nomor dan dua PDF untuk pengajuan dua instansi', function () {
    $admin = apiUser('admin_kpap');
    $mahasiswa = apiUser();
    $pengajuan = apiPengajuan($mahasiswa, [
        'status' => StatusPengajuan::MenungguVerifikasi->value,
        'instansi_kedua' => 'CV Instansi Kedua',
        'alamat_instansi_kedua' => 'Jl. Kedua',
        'tanggal_mulai_kedua' => now()->addDays(15)->toDateString(),
        'tanggal_selesai_kedua' => now()->addDays(45)->toDateString(),
    ]);

    $suratMagang = Mockery::mock(SuratMagangService::class);
    $suratMagang->shouldReceive('simpan')
        ->twice()
        ->andReturnUsing(function ($model, $urutan) {
            $path = "pengajuan/{$model->id}/surat-magang-{$urutan}.pdf";
            Storage::disk('local')->put($path, '%PDF-surat-magang');

            return $path;
        });
    app()->instance(SuratMagangService::class, $suratMagang);

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/pengajuan-magang/{$pengajuan->id}/status", [
            'status' => 'setuju',
        ])
        ->assertOk();

    $pengajuan->refresh();

    expect($pengajuan->nomor_surat)->not->toBeNull()
        ->and($pengajuan->nomor_surat_kedua)->not->toBeNull()
        ->and($pengajuan->nomor_surat)->not->toBe($pengajuan->nomor_surat_kedua)
        ->and(Storage::disk('local')->exists($pengajuan->file_surat_magang))->toBeTrue()
        ->and(Storage::disk('local')->exists($pengajuan->file_surat_magang_kedua))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| 6. Verifikasi QR, berkas bertanda tangan, dan unduhan surat
|--------------------------------------------------------------------------
*/

it('endpoint verifikasi QR menampilkan data surat yang valid tanpa login', function () {
    $user = apiUser();
    Storage::disk('local')->put('pengajuan/surat-pengajuan.pdf', '%PDF-surat');

    $pengajuan = apiPengajuan($user, [
        'status' => StatusPengajuan::Setuju->value,
        'ditandatangani_at' => now(),
        'file_surat_pengajuan' => 'pengajuan/surat-pengajuan.pdf',
        'nomor_surat' => '1/IT3.F4/PK.01.06/'.now()->year,
    ]);

    $this->getJson("/api/verifikasi/{$pengajuan->kode_verifikasi}")
        ->assertOk()
        ->assertJsonPath('valid', true)
        ->assertJsonPath('data.nama', $user->name)
        ->assertJsonPath('data.nim', $user->nim)
        ->assertJsonPath('data.instansi', $pengajuan->instansi);
});

it('endpoint verifikasi QR menolak kode yang tidak ditemukan', function () {
    $this->getJson('/api/verifikasi/ABCDEFGHIJKLMNOPQRSTUVWXYZ123456')
        ->assertNotFound()
        ->assertJsonPath('valid', false);
});

it('endpoint verifikasi QR menolak surat yang tanda tangannya sudah dibatalkan', function () {
    $user = apiUser();
    $pengajuan = apiPengajuan($user, [
        'ditandatangani_at' => null,
        'file_surat_pengajuan' => 'pengajuan/surat.pdf',
    ]);

    $this->getJson("/api/verifikasi/{$pengajuan->kode_verifikasi}")
        ->assertNotFound()
        ->assertJsonPath('valid', false);
});

it('verifikasi QR untuk instansi kedua mengembalikan instansi kedua', function () {
    $user = apiUser();
    $pengajuan = apiPengajuan($user, [
        'ditandatangani_at' => now(),
        'instansi_kedua' => 'CV Instansi Kedua',
        'alamat_instansi_kedua' => 'Jl. Kedua',
        'tanggal_mulai_kedua' => now()->addDays(15)->toDateString(),
        'tanggal_selesai_kedua' => now()->addDays(45)->toDateString(),
        'file_surat_pengajuan_kedua' => 'pengajuan/surat-kedua.pdf',
    ]);

    $this->getJson("/api/verifikasi/{$pengajuan->kode_verifikasi}?urutan=2")
        ->assertOk()
        ->assertJsonPath('valid', true)
        ->assertJsonPath('data.instansi', 'CV Instansi Kedua');
});

it('signed URL berkas admin dapat menampilkan berkas yang tersedia', function () {
    $user = apiUser();
    Storage::disk('local')->put('pengajuan/bukti-spp.pdf', '%PDF-spp');

    $pengajuan = apiPengajuan($user, [
        'status' => StatusPengajuan::MenungguVerifikasi->value,
        'file_bukti_spp' => 'pengajuan/bukti-spp.pdf',
    ]);

    $url = URL::temporarySignedRoute(
        'admin.pengajuan.berkas',
        now()->addMinutes(10),
        [
            'pengajuan' => $pengajuan->id,
            'jenis' => 'bukti-spp',
            'mode' => 'preview',
        ]
    );

    $this->get($url)->assertOk();
});

it('signed URL berkas menolak URL yang signature-nya tidak valid', function () {
    $user = apiUser();
    $pengajuan = apiPengajuan($user, [
        'status' => StatusPengajuan::MenungguVerifikasi->value,
        'file_bukti_spp' => 'pengajuan/bukti-spp.pdf',
    ]);

    $this->get("/api/admin/pengajuan/{$pengajuan->id}/berkas/bukti-spp")
        ->assertForbidden();
});

it('signed URL berkas tidak membuka berkas upload ketika pengajuan masih draft', function () {
    $user = apiUser();
    Storage::disk('local')->put('pengajuan/bukti-spp.pdf', '%PDF-spp');

    $pengajuan = apiPengajuan($user, [
        'file_bukti_spp' => 'pengajuan/bukti-spp.pdf',
    ]);

    $url = URL::temporarySignedRoute(
        'admin.pengajuan.berkas',
        now()->addMinutes(10),
        [
            'pengajuan' => $pengajuan->id,
            'jenis' => 'bukti-spp',
            'mode' => 'preview',
        ]
    );

    $this->get($url)->assertNotFound();
});

it('signed download surat magang dapat mengunduh surat yang sudah disetujui', function () {
    $user = apiUser();
    Storage::disk('local')->put('pengajuan/surat-magang.pdf', '%PDF-surat-magang');

    $pengajuan = apiPengajuan($user, [
        'status' => StatusPengajuan::Setuju->value,
        'file_surat_magang' => 'pengajuan/surat-magang.pdf',
    ]);

    $url = URL::temporarySignedRoute(
        'surat-magang.unduh',
        now()->addMinutes(10),
        ['pengajuan' => $pengajuan->id, 'urutan' => 1]
    );

    $this->get($url)->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('signed download surat magang menolak pengajuan yang belum disetujui', function () {
    $user = apiUser();
    Storage::disk('local')->put('pengajuan/surat-magang.pdf', '%PDF-surat-magang');

    $pengajuan = apiPengajuan($user, [
        'status' => StatusPengajuan::MenungguVerifikasi->value,
        'file_surat_magang' => 'pengajuan/surat-magang.pdf',
    ]);

    $url = URL::temporarySignedRoute(
        'surat-magang.unduh',
        now()->addMinutes(10),
        ['pengajuan' => $pengajuan->id, 'urutan' => 1]
    );

    $this->get($url)->assertNotFound();
});

it('signed download surat magang menolak urutan instansi yang tidak tersedia', function () {
    $user = apiUser();
    $pengajuan = apiPengajuan($user, [
        'status' => StatusPengajuan::Setuju->value,
        'file_surat_magang' => 'pengajuan/surat-magang.pdf',
    ]);

    $url = URL::temporarySignedRoute(
        'surat-magang.unduh',
        now()->addMinutes(10),
        ['pengajuan' => $pengajuan->id, 'urutan' => 2]
    );

    $this->get($url)->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| 7. Pembuatan link WhatsApp oleh admin
|--------------------------------------------------------------------------
*/

it('admin dapat membuat link WhatsApp untuk surat instansi pertama', function () {
    $admin = apiUser('admin_kpap');
    $mahasiswa = apiUser('mahasiswa', ['no_wa' => '081234567890']);
    Storage::disk('local')->put('pengajuan/surat-magang.pdf', '%PDF-surat-magang');

    $pengajuan = apiPengajuan($mahasiswa, [
        'status' => StatusPengajuan::Setuju->value,
        'nomor_surat' => '1/IT3.F4/PK.01.06/'.now()->year,
        'file_surat_magang' => 'pengajuan/surat-magang.pdf',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/admin/pengajuan-magang/{$pengajuan->id}/surat-magang/1/whatsapp")
        ->assertOk()
        ->assertJsonPath('data.urutan', 1)
        ->assertJsonPath('data.nomor_tujuan', '6281234567890')
        ->assertJsonPath('data.instansi', $pengajuan->instansi)
        ->assertJsonPath('message', 'Link WhatsApp berhasil dibuat. Pesan belum terkirim, kirim secara manual dari WhatsApp.')
        ->assertJsonStructure(['data' => [
            'download_url',
            'whatsapp_url',
            'pesan',
            'berlaku_sampai',
        ]]);
});

it('admin tidak dapat membuat link WhatsApp sebelum pengajuan disetujui', function () {
    $admin = apiUser('admin_kpap');
    $mahasiswa = apiUser();
    $pengajuan = apiPengajuan($mahasiswa, [
        'status' => StatusPengajuan::MenungguVerifikasi->value,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/admin/pengajuan-magang/{$pengajuan->id}/surat-magang/1/whatsapp")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('admin tidak dapat membuat link WhatsApp jika surat belum tersedia', function () {
    $admin = apiUser('admin_kpap');
    $mahasiswa = apiUser();
    $pengajuan = apiPengajuan($mahasiswa, [
        'status' => StatusPengajuan::Setuju->value,
        'file_surat_magang' => null,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/admin/pengajuan-magang/{$pengajuan->id}/surat-magang/1/whatsapp")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['urutan']);
});

it('admin tidak dapat membuat link WhatsApp jika nomor mahasiswa tidak valid', function () {
    $admin = apiUser('admin_kpap');
    $mahasiswa = apiUser('mahasiswa', ['no_wa' => 'nomor-salah']);
    Storage::disk('local')->put('pengajuan/surat-magang.pdf', '%PDF-surat');

    $pengajuan = apiPengajuan($mahasiswa, [
        'status' => StatusPengajuan::Setuju->value,
        'file_surat_magang' => 'pengajuan/surat-magang.pdf',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/admin/pengajuan-magang/{$pengajuan->id}/surat-magang/1/whatsapp")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['no_wa']);
});

it('admin tidak dapat membuat link WhatsApp untuk instansi kedua jika tidak ada', function () {
    $admin = apiUser('admin_kpap');
    $mahasiswa = apiUser();
    $pengajuan = apiPengajuan($mahasiswa, [
        'status' => StatusPengajuan::Setuju->value,
        'file_surat_magang' => 'pengajuan/surat-magang.pdf',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/admin/pengajuan-magang/{$pengajuan->id}/surat-magang/2/whatsapp")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['urutan']);
});

/*
|--------------------------------------------------------------------------
| 8. Aturan model yang dipakai oleh API/resource
|--------------------------------------------------------------------------
*/

it('menghitung jumlah pengajuan terkirim tanpa menghitung draft', function () {
    $user = apiUser();

    apiPengajuan($user, ['status' => StatusPengajuan::Draft->value]);
    apiPengajuan($user, [
        'status' => StatusPengajuan::MenungguVerifikasi->value,
        'kode_verifikasi' => Str::random(32),
    ]);
    apiPengajuan($user, [
        'status' => StatusPengajuan::Revisi->value,
        'kode_verifikasi' => Str::random(32),
    ]);

    expect(PengajuanMagang::jumlahTerkirimUser($user->id))->toBe(2);
});

it('menghitung anggota tanpa menggandakan data ketua', function () {
    $user = apiUser();
    $pengajuan = apiPengajuan($user, [
        'anggota' => [
            ['user_id' => null, 'nama' => $user->name, 'nim' => $user->nim, 'departemen' => 'MNH'],
            ['user_id' => null, 'nama' => 'Anggota Satu', 'nim' => 'E2412300002', 'departemen' => 'HH'],
            ['user_id' => 999, 'nama' => 'Snapshot Lama', 'nim' => 'E3412300003', 'departemen' => 'KSHE'],
        ],
    ]);

    expect($pengajuan->jumlahMahasiswa())->toBe(2)
        ->and($pengajuan->anggotaTambahan())->toHaveCount(1);
});

it('membatasi daftar anggota cetak sesuai konfigurasi maksimal anggota', function () {
    config(['magang.maks_anggota' => 2]);

    $user = apiUser();
    $pengajuan = apiPengajuan($user, [
        'anggota' => [
            ['nama' => 'Anggota Satu', 'nim' => 'E2412300002', 'departemen' => 'HH'],
            ['nama' => 'Anggota Dua', 'nim' => 'E3412300003', 'departemen' => 'KSHE'],
        ],
    ]);

    expect($pengajuan->daftarAnggotaCetak())->toHaveCount(2);
});


