<?php
// ============================================================
//  MediaVault - Malaysian IC Parser
//  Member 1: NURHANIM NABILA BINTI AB RAZAK
//  Extracts: Date of Birth, State of Origin, Age, Gender
// ============================================================

/**
 * Parse Malaysian IC Number (YYMMDD-SS-GGGG)
 * Returns array with dob, state, age, gender
 */
function parseMyKadIC($ic) {
    // Remove dashes if any (e.g. 020115-12-1234 -> 020115121234)
    $ic = preg_replace('/[^0-9]/', '', $ic);

    if (strlen($ic) !== 12) {
        return null; // Invalid IC
    }

    // ── 1. Date of Birth ─────────────────────────────────────
    $yy = substr($ic, 0, 2);
    $mm = substr($ic, 2, 2);
    $dd = substr($ic, 4, 2);

    // Determine century: if YY > current 2-digit year, born in 1900s
    $currentYY = (int) date('y');
    $year = ((int)$yy > $currentYY) ? "19$yy" : "20$yy";

    $dob = "$year-$mm-$dd"; // Format: YYYY-MM-DD

    // ── 2. Age ───────────────────────────────────────────────
    $birthDate = new DateTime($dob);
    $today     = new DateTime();
    $age       = $today->diff($birthDate)->y;

    // ── 3. State of Origin ──────────────────────────────────
    $stateCode = (int) substr($ic, 6, 2);
    $stateMap  = [
        1  => 'Johor',
        2  => 'Kedah',
        3  => 'Kelantan',
        4  => 'Melaka',
        5  => 'Negeri Sembilan',
        6  => 'Pahang',
        7  => 'Penang',
        8  => 'Perak',
        9  => 'Perlis',
        10 => 'Selangor',
        11 => 'Terengganu',
        12 => 'Sabah',
        13 => 'Sarawak',
        14 => 'Federal Territory (KL)',
        15 => 'Labuan',
        16 => 'Putrajaya',
        21 => 'Johor',
        22 => 'Johor',
        23 => 'Johor',
        24 => 'Johor',
        25 => 'Kedah',
        26 => 'Kedah',
        27 => 'Kedah',
        28 => 'Kelantan',
        29 => 'Kelantan',
        30 => 'Melaka',
        31 => 'Negeri Sembilan',
        32 => 'Pahang',
        33 => 'Pahang',
        34 => 'Penang',
        35 => 'Penang',
        36 => 'Perak',
        37 => 'Perak',
        38 => 'Perak',
        39 => 'Perak',
        40 => 'Perlis',
        41 => 'Selangor',
        42 => 'Selangor',
        43 => 'Selangor',
        44 => 'Selangor',
        45 => 'Terengganu',
        46 => 'Terengganu',
        47 => 'Sabah',
        48 => 'Sabah',
        49 => 'Sabah',
        50 => 'Sarawak',
        51 => 'Sarawak',
        52 => 'Sarawak',
        53 => 'Sarawak',
        54 => 'Federal Territory (KL)',
        55 => 'Federal Territory (KL)',
        56 => 'Federal Territory (KL)',
        57 => 'Federal Territory (KL)',
        58 => 'Labuan',
        59 => 'Negeri Sembilan',
        82 => 'Sabah',
        83 => 'Sarawak (Unverified)',
        84 => 'Sarawak (Unverified)',
    ];
    $state = $stateMap[$stateCode] ?? 'Unknown / Foreign Born';

    // ── 4. Gender ────────────────────────────────────────────
    $lastFour = (int) substr($ic, 8, 4);
    $gender   = ($lastFour % 2 === 0) ? 'Female' : 'Male';

    return [
        'dob'    => date('d/m/Y', strtotime($dob)), // Display format
        'dob_db' => $dob,                            // DB storage format
        'age'    => $age,
        'state'  => $state,
        'gender' => $gender,
    ];
}
?>
