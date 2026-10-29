<?php

/**
 * Pemetaan prefix NIM (4 karakter pertama) => kode departemen.
 * Harus sejalan dengan regex NIM di RegisterRequest (E141, E241, E341, E441),
 * dan kodenya sama dengan kunci di config/departemen.php serta isi kolom users.departemen.
 */
return [
    'E141' => 'MNH',
    'E241' => 'THH',
    'E341' => 'KSHE',
    'E441' => 'SVK',
];