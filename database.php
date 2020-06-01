<?php

if (session_id() == '') {
    session_start();
}

function viewAll()
{
    $allowed = [];
    $allowed = array_merge($allowed, unserialize(ALLOWED_IDS));

    if (in_array(getUserID(), $allowed)) {
        return true;
    }

    return false;
}

function getUserID()
{
    return isset($_SESSION['codigoIdentificacao']) ? $_SESSION['codigoIdentificacao'] : null;
}

function getUserName()
{
    return isset($_SESSION['nomeUsuario']) ? $_SESSION['nomeUsuario'] : 'Não informado';
}

function saveAppointment(\PDO $db,
                         $userID,
                         $name,
                         $address,
                         $landlinePhoneNumber,
                         $mobilePhoneNumber,
                         $email,
                         $numberOfEmployees,
                         $date,
                         $returnDate,
                         $observations)
{
    $stmt = $db->prepare("INSERT INTO appointments VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if ($date) {
        $dateTime = new \DateTime($date);
        $date = date_format($dateTime, "Y-m-d");
    }

    if ($returnDate) {
        $dateTime = new \DateTime($returnDate);
        $returnDate = date_format($dateTime, "Y-m-d");
    }

    $user = getUserName();

    $stmt->execute([
        null,
        $userID,
        $user,
        $name,
        $address,
        $landlinePhoneNumber,
        $mobilePhoneNumber,
        $email,
        $numberOfEmployees,
        $date,
        $returnDate,
        $observations
    ]);
    return $db->lastInsertId();
}

function updateAppointment(\PDO $db,
                           $appointmentID,
                           $name,
                           $address,
                           $landlinePhoneNumber,
                           $mobilePhoneNumber,
                           $email,
                           $numberOfEmployees,
                           $date,
                           $returnDate,
                           $observations)
{
    $stmt = $db->prepare("UPDATE appointments SET name = ?, address = ?, landline_phone_number = ?, mobile_phone_number = ?, email = ?, number_of_employees = ?, date = ?, return_date = ?, observations = ? WHERE id = ?");

    if ($date) {
        $dateTime = new \DateTime($date);
        $date = date_format($dateTime, "Y-m-d");
    }

    if ($returnDate) {
        $dateTime = new \DateTime($returnDate);
        $returnDate = date_format($dateTime, "Y-m-d");
    }

    $stmt->execute([
        $name,
        $address,
        $landlinePhoneNumber,
        $mobilePhoneNumber,
        $email,
        $numberOfEmployees,
        $date,
        $returnDate,
        $observations,
        $appointmentID
    ]);
    return $appointmentID;
}

function saveAppointmentFile(\PDO $db, $appointmentID, $name, $path)
{
    $stmt = $db->prepare("INSERT INTO appointment_files VALUES(?, ?, ?, ?, ?)");
    $stmt->execute([
        null,
        $appointmentID,
        uuid(),
        $name,
        $path
    ]);
    return $db->lastInsertId();
}

function getAppointment(\PDO $db, $id)
{
    $stmt = $db->prepare("SELECT * FROM appointments WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $result = $stmt->fetch();

    if ($result) {
        $result['files'] = getAppointmentFilesForAppointment($db, $id);
    }

    return $result;
}

function getAppointments(\PDO $db, $userID, $date, $returnDate)
{
    $conditions = [];
    $parameters = [];

    if ($date) {
        $dateTime = new \DateTime($date);
        $date = date_format($dateTime, "Y-m-d");
    }

    if ($returnDate) {
        $dateTime = new \DateTime($returnDate);
        $returnDate = date_format($dateTime, "Y-m-d");
    }

    if ($userID && !viewAll()) {
        $conditions[] = 'alianca_user_id = ?';
        $parameters[] = $userID;
    }

    if ($date) {
        $conditions[] = 'date = ?';
        $parameters[] = $date;
    }

    if ($returnDate) {
        $conditions[] = 'return_date = ?';
        $parameters[] = $returnDate;
    }

    $query = "SELECT * FROM appointments";

    if ($conditions) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }

    $stmt = $db->prepare($query);
    $stmt->execute($parameters);
    $results = $stmt->fetchAll();

    foreach ($results as &$result) {
        $result['files'] = getAppointmentFilesForAppointment($db, $result['id']);
    }
    unset($result);

    return $results;
}

function getAppointmentFile(\PDO $db, $uuid)
{
    $stmt = $db->prepare("SELECT * FROM appointment_files WHERE uuid = ? LIMIT 1");
    $stmt->execute([$uuid]);
    return $stmt->fetch();
}

function getAppointmentFilesForAppointment(\PDO $db, $appointmentID)
{
    $stmt = $db->prepare("SELECT * FROM appointment_files WHERE appointment_id = ?");
    $stmt->execute([$appointmentID]);
    $files = $stmt->fetchAll();

    foreach ($files as &$file) {
        $file['url'] = getBaseURL() . '/api/download/' . $file['uuid'];
    }
    unset($file);

    return $files;
}

function setupDatabase(\PDO $db)
{
    $db->exec("
    CREATE TABLE IF NOT EXISTS appointments (
        id int(10) unsigned NOT NULL AUTO_INCREMENT,
        alianca_user_id int(10) unsigned DEFAULT NULL,
        user varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL, 
        name varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        address varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        landline_phone_number varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        mobile_phone_number varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        email varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        number_of_employees varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        date datetime DEFAULT NULL,
        return_date datetime DEFAULT NULL,
        observations text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        PRIMARY KEY (id)
    )");

    $db->exec("
    CREATE TABLE IF NOT EXISTS appointment_files (
        id int(10) unsigned NOT NULL AUTO_INCREMENT,
        appointment_id int(10) unsigned NOT NULL,
        uuid varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        name varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        path varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        PRIMARY KEY (id),
        KEY appointment_files_appointment_id_foreign (appointment_id),
        CONSTRAINT appointment_files_appointment_id_foreign FOREIGN KEY (appointment_id) REFERENCES appointments (id) ON DELETE CASCADE
    )");
}

function uuid()
{
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
