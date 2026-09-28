<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$action = request_action();
$method = request_method();
$pdo = db();
$user = current_user();

function event_poster_upload_dir(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'events';
}

function ensure_directory(string $path): void
{
    if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create upload directory');
    }
}

function decode_poster_json(?string $json): ?array
{
    if (!$json) {
        return null;
    }

    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : null;
}

function poster_to_public_array(?array $poster): ?array
{
    if (!$poster) {
        return null;
    }

    return [
        'name' => $poster['name'] ?? null,
        'size' => isset($poster['size']) ? (int)$poster['size'] : null,
        'type' => $poster['type'] ?? null,
        'path' => $poster['path'] ?? null,
        'url' => $poster['url'] ?? null,
    ];
}

function save_event_poster(?array $poster, ?array $currentPoster = null): ?array
{
    if ($poster === null) {
        return $currentPoster;
    }

    if (!is_array($poster)) {
        return $currentPoster;
    }

    $dataUrl = trim((string)($poster['dataUrl'] ?? ''));
    if ($dataUrl === '') {
        return poster_to_public_array($poster) ?? $currentPoster;
    }

    if (!preg_match('#^data:(image/(?:png|jpe?g|gif|webp));base64,(.+)$#i', $dataUrl, $matches)) {
        throw new RuntimeException('Invalid poster image data');
    }

    $mime = strtolower($matches[1]);
    $base64 = $matches[2];
    $binary = base64_decode($base64, true);
    if ($binary === false) {
        throw new RuntimeException('Unable to decode poster image');
    }

    $extensionMap = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $extension = $extensionMap[$mime] ?? 'png';
    $dir = event_poster_upload_dir();
    ensure_directory($dir);

    $filename = 'event-' . bin2hex(random_bytes(8)) . '.' . $extension;
    $absolutePath = $dir . DIRECTORY_SEPARATOR . $filename;
    if (file_put_contents($absolutePath, $binary) === false) {
        throw new RuntimeException('Unable to save poster image');
    }

    $relativePath = 'uploads/events/' . $filename;

    return [
        'name' => (string)($poster['name'] ?? $filename),
        'size' => strlen($binary),
        'type' => $mime,
        'path' => $relativePath,
        'url' => $relativePath,
    ];
}

function map_public_event_row(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'title' => $row['title'],
        'description' => $row['description'],
        'startDate' => $row['start_date'],
        'startTime' => $row['start_time'] ?? null,
        'endDate' => $row['end_date'],
        'endTime' => $row['end_time'] ?? null,
        'location' => $row['location'],
        'venueType' => $row['venue_type'],
        'venueNumber' => $row['venue_number'],
        'category' => $row['category'],
        'maxParticipants' => (int)$row['max_participants'],
        'status' => $row['status'],
        'participantCount' => (int)($row['participant_count'] ?? 0),
    ];
}

try {
    if ($action === 'getpublic' && $method === 'GET') {
        $stmt = $pdo->query(
            'SELECT e.*,
                    (
                        SELECT COUNT(*)
                        FROM event_registrations r
                        WHERE r.event_id = e.id
                    ) AS participant_count
             FROM events e
             WHERE e.status = \'approved\'
             ORDER BY e.start_date ASC, e.id ASC'
        );
        $rows = $stmt->fetchAll();
        $events = array_map('map_public_event_row', $rows);

        respond(['success' => true, 'message' => 'Public events loaded', 'data' => $events]);
    }

    if ($action === 'getall' && $method === 'GET') {
        require_auth();

        $stmt = $pdo->query(
            'SELECT e.*,
                    (
                        SELECT COUNT(*)
                        FROM event_registrations r
                        WHERE r.event_id = e.id
                    ) AS participant_count
             FROM events e
             ORDER BY e.created_at DESC, e.id DESC'
        );
        $rows = $stmt->fetchAll();
        $eventIds = array_map(static fn(array $row) => (int)$row['id'], $rows);
        $participantsMap = [];
        if ($eventIds) {
            $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
            $regStmt = $pdo->prepare(
                "SELECT event_id, user_id FROM event_registrations WHERE event_id IN ($placeholders) ORDER BY id ASC"
            );
            $regStmt->execute($eventIds);
            while ($reg = $regStmt->fetch()) {
                $eid = (int)$reg['event_id'];
                $participantsMap[$eid] ??= [];
                $participantsMap[$eid][] = (int)$reg['user_id'];
            }
        }

        $events = array_map(static function (array $row) use ($participantsMap): array {
            $eventId = (int)$row['id'];
            return [
                'id' => $eventId,
                'title' => $row['title'],
                'description' => $row['description'],
                'startDate' => $row['start_date'],
                'startTime' => $row['start_time'],
                'endDate' => $row['end_date'],
                'endTime' => $row['end_time'],
                'registrationDeadline' => $row['registration_deadline'],
                'location' => $row['location'],
                'venueType' => $row['venue_type'],
                'venueNumber' => $row['venue_number'],
                'category' => $row['category'],
                'maxParticipants' => (int)$row['max_participants'],
                'registrationLink' => $row['registration_link'],
                'reminderDays' => (int)$row['reminder_days'],
                'accessType' => $row['access_type'],
                'feeAmount' => (float)$row['fee_amount'],
                'paymentMethod' => $row['payment_method'],
                'paymentInstructions' => $row['payment_instructions'],
                'poster' => decode_poster_json($row['poster_json']),
                'organizerId' => (int)$row['organizer_id'],
                'organizerName' => $row['organizer_name'],
                'organizerEmail' => $row['organizer_email'],
                'organizerDept' => $row['organizer_dept'],
                'status' => $row['status'],
                'participants' => $participantsMap[$eventId] ?? [],
                'participantCount' => (int)$row['participant_count'],
                'createdAt' => $row['created_at'],
                'updatedAt' => $row['updated_at'],
            ];
        }, $rows);

        respond(['success' => true, 'message' => 'Events loaded', 'data' => $events]);
    }

    if ($action === 'create' && $method === 'POST') {
        $user = require_auth();
        $data = request_data();

        $title = trim((string)($data['title'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        $startDate = (string)($data['startDate'] ?? '');
        $startTime = (string)($data['startTime'] ?? '');
        $endDate = (string)($data['endDate'] ?? '');
        $endTime = (string)($data['endTime'] ?? '');
        $location = trim((string)($data['location'] ?? ''));
        $venueType = trim((string)($data['venueType'] ?? ''));
        $venueNumber = trim((string)($data['venueNumber'] ?? ''));
        $category = trim((string)($data['category'] ?? ''));
        $maxParticipants = max(1, (int)($data['maxParticipants'] ?? 100));
        $registrationLink = trim((string)($data['registrationLink'] ?? ''));
        $reminderDays = (int)($data['reminderDays'] ?? 0);
        $accessType = strtolower(trim((string)($data['accessType'] ?? 'free')));
        $feeAmount = (float)($data['feeAmount'] ?? 0);
        $paymentMethod = trim((string)($data['paymentMethod'] ?? ''));
        $paymentInstructions = trim((string)($data['paymentInstructions'] ?? ''));
        $registrationDeadline = trim((string)($data['registrationDeadline'] ?? '')) ?: null;
        $status = $user['role'] === 'admin' ? 'approved' : 'pending';

        if ($title === '' || $description === '' || $startDate === '' || $startTime === '' || $endDate === '' || $endTime === '' || $location === '' || $venueType === '' || $venueNumber === '' || $category === '') {
            respond(['success' => false, 'message' => 'Missing required event fields'], 422);
        }

        $poster = isset($data['poster']) ? (is_array($data['poster']) ? $data['poster'] : null) : null;
        $posterRecord = save_event_poster($poster);
        $posterJson = $posterRecord !== null ? json_encode($posterRecord, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        $stmt = $pdo->prepare(
            'INSERT INTO events (
                title, description, start_date, start_time, end_date, end_time,
                registration_deadline, location, venue_type, venue_number, category,
                max_participants, registration_link, reminder_days, access_type, fee_amount,
                payment_method, payment_instructions, poster_json, organizer_id, organizer_name,
                organizer_email, organizer_dept, status, created_at
            ) VALUES (
                :title, :description, :start_date, :start_time, :end_date, :end_time,
                :registration_deadline, :location, :venue_type, :venue_number, :category,
                :max_participants, :registration_link, :reminder_days, :access_type, :fee_amount,
                :payment_method, :payment_instructions, :poster_json, :organizer_id, :organizer_name,
                :organizer_email, :organizer_dept, :status, NOW()
            )'
        );
        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':start_date' => $startDate,
            ':start_time' => $startTime,
            ':end_date' => $endDate,
            ':end_time' => $endTime,
            ':registration_deadline' => $registrationDeadline,
            ':location' => $location,
            ':venue_type' => $venueType,
            ':venue_number' => $venueNumber,
            ':category' => $category,
            ':max_participants' => $maxParticipants,
            ':registration_link' => $registrationLink ?: null,
            ':reminder_days' => $reminderDays,
            ':access_type' => in_array($accessType, ['free', 'paid'], true) ? $accessType : 'free',
            ':fee_amount' => $feeAmount,
            ':payment_method' => $paymentMethod ?: null,
            ':payment_instructions' => $paymentInstructions ?: null,
            ':poster_json' => $posterJson,
            ':organizer_id' => $user['id'],
            ':organizer_name' => $user['name'],
            ':organizer_email' => $user['email'],
            ':organizer_dept' => $user['department'] ?? null,
            ':status' => $status,
        ]);

        $eventId = (int)$pdo->lastInsertId();
        create_notification_record(
            null,
            'admin',
            'New event submitted',
            sprintf('"%s" was submitted by %s.', $title, $user['name']),
            $status === 'approved' ? 'success' : 'info',
            'event_create',
            ['event_id' => $eventId]
        );

        respond([
            'success' => true,
            'message' => $status === 'approved' ? 'Event created and approved' : 'Event created and submitted for approval',
            'data' => ['id' => $eventId, 'status' => $status],
        ], 201);
    }

    if ($action === 'update' && in_array($method, ['PUT', 'POST'], true)) {
        $user = require_auth();
        $eventId = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
        if ($eventId <= 0) {
            respond(['success' => false, 'message' => 'Invalid event id'], 422);
        }

        $eventStmt = $pdo->prepare('SELECT * FROM events WHERE id = :id LIMIT 1');
        $eventStmt->execute([':id' => $eventId]);
        $existing = $eventStmt->fetch();
        if (!$existing) {
            respond(['success' => false, 'message' => 'Event not found'], 404);
        }

        if ($user['role'] !== 'admin' && (int)$existing['organizer_id'] !== (int)$user['id']) {
            respond(['success' => false, 'message' => 'Access denied'], 403);
        }

        $data = request_data();
        $title = trim((string)($data['title'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        $startDate = (string)($data['startDate'] ?? '');
        $startTime = (string)($data['startTime'] ?? '');
        $endDate = (string)($data['endDate'] ?? '');
        $endTime = (string)($data['endTime'] ?? '');
        $location = trim((string)($data['location'] ?? ''));
        $venueType = trim((string)($data['venueType'] ?? ''));
        $venueNumber = trim((string)($data['venueNumber'] ?? ''));
        $category = trim((string)($data['category'] ?? ''));
        $maxParticipants = max(1, (int)($data['maxParticipants'] ?? 100));
        $registrationLink = trim((string)($data['registrationLink'] ?? ''));
        $reminderDays = (int)($data['reminderDays'] ?? 0);
        $accessType = strtolower(trim((string)($data['accessType'] ?? 'free')));
        $feeAmount = (float)($data['feeAmount'] ?? 0);
        $paymentMethod = trim((string)($data['paymentMethod'] ?? ''));
        $paymentInstructions = trim((string)($data['paymentInstructions'] ?? ''));
        $registrationDeadline = trim((string)($data['registrationDeadline'] ?? '')) ?: null;
        $poster = array_key_exists('poster', $data) && is_array($data['poster']) ? $data['poster'] : null;
        $posterRecord = $poster !== null ? save_event_poster($poster, decode_poster_json($existing['poster_json'])) : decode_poster_json($existing['poster_json']);

        if ($title === '' || $description === '' || $startDate === '' || $startTime === '' || $endDate === '' || $endTime === '' || $location === '' || $venueType === '' || $venueNumber === '' || $category === '') {
            respond(['success' => false, 'message' => 'Missing required event fields'], 422);
        }

        $stmt = $pdo->prepare(
            'UPDATE events SET
                title = :title,
                description = :description,
                start_date = :start_date,
                start_time = :start_time,
                end_date = :end_date,
                end_time = :end_time,
                registration_deadline = :registration_deadline,
                location = :location,
                venue_type = :venue_type,
                venue_number = :venue_number,
                category = :category,
                max_participants = :max_participants,
                registration_link = :registration_link,
                reminder_days = :reminder_days,
                access_type = :access_type,
                fee_amount = :fee_amount,
                payment_method = :payment_method,
                payment_instructions = :payment_instructions,
                poster_json = :poster_json,
                status = :status,
                updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':start_date' => $startDate,
            ':start_time' => $startTime,
            ':end_date' => $endDate,
            ':end_time' => $endTime,
            ':registration_deadline' => $registrationDeadline,
            ':location' => $location,
            ':venue_type' => $venueType,
            ':venue_number' => $venueNumber,
            ':category' => $category,
            ':max_participants' => $maxParticipants,
            ':registration_link' => $registrationLink ?: null,
            ':reminder_days' => $reminderDays,
            ':access_type' => in_array($accessType, ['free', 'paid'], true) ? $accessType : 'free',
            ':fee_amount' => $feeAmount,
            ':payment_method' => $paymentMethod ?: null,
            ':payment_instructions' => $paymentInstructions ?: null,
            ':poster_json' => $posterRecord !== null ? json_encode($posterRecord, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ':status' => $existing['status'],
            ':id' => $eventId,
        ]);

        respond(['success' => true, 'message' => 'Event updated successfully', 'data' => ['id' => $eventId]]);
    }

    if ($action === 'delete' && in_array($method, ['DELETE', 'POST'], true)) {
        $user = require_auth();
        $eventId = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
        if ($eventId <= 0) {
            respond(['success' => false, 'message' => 'Invalid event id'], 422);
        }

        $eventStmt = $pdo->prepare('SELECT id, organizer_id FROM events WHERE id = :id LIMIT 1');
        $eventStmt->execute([':id' => $eventId]);
        $existing = $eventStmt->fetch();
        if (!$existing) {
            respond(['success' => false, 'message' => 'Event not found'], 404);
        }

        if ($user['role'] !== 'admin' && (int)$existing['organizer_id'] !== (int)$user['id']) {
            respond(['success' => false, 'message' => 'Access denied'], 403);
        }

        $pdo->prepare('DELETE FROM events WHERE id = :id')->execute([':id' => $eventId]);
        respond(['success' => true, 'message' => 'Event deleted successfully']);
    }

    if ($action === 'register' && $method === 'POST') {
        $user = require_auth();
        $eventId = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
        if ($eventId <= 0) {
            respond(['success' => false, 'message' => 'Invalid event id'], 422);
        }

        $eventStmt = $pdo->prepare('SELECT * FROM events WHERE id = :id LIMIT 1');
        $eventStmt->execute([':id' => $eventId]);
        $event = $eventStmt->fetch();
        if (!$event) {
            respond(['success' => false, 'message' => 'Event not found'], 404);
        }

        $check = $pdo->prepare('SELECT id FROM event_registrations WHERE event_id = :event_id AND user_id = :user_id LIMIT 1');
        $check->execute([':event_id' => $eventId, ':user_id' => $user['id']]);
        if ($check->fetch()) {
            respond(['success' => false, 'message' => 'Already registered'], 409);
        }

        $countStmt = $pdo->prepare('SELECT COUNT(*) AS total FROM event_registrations WHERE event_id = :event_id');
        $countStmt->execute([':event_id' => $eventId]);
        $currentCount = (int)($countStmt->fetch()['total'] ?? 0);
        if ($currentCount >= (int)$event['max_participants']) {
            respond(['success' => false, 'message' => 'Event is full'], 409);
        }

        $pdo->prepare('INSERT INTO event_registrations (event_id, user_id, created_at) VALUES (:event_id, :user_id, NOW())')
            ->execute([':event_id' => $eventId, ':user_id' => $user['id']]);

        create_notification_record(
            (int)$event['organizer_id'],
            null,
            'New registration',
            sprintf('%s registered for "%s".', $user['name'], $event['title']),
            'info',
            'event_register',
            ['event_id' => $eventId, 'user_id' => $user['id']]
        );

        respond([
            'success' => true,
            'message' => 'You are now registered',
        ]);
    }

    if ($action === 'unregister' && $method === 'POST') {
        $user = require_auth();
        $eventId = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
        if ($eventId <= 0) {
            respond(['success' => false, 'message' => 'Invalid event id'], 422);
        }

        $stmt = $pdo->prepare('DELETE FROM event_registrations WHERE event_id = :event_id AND user_id = :user_id');
        $stmt->execute([':event_id' => $eventId, ':user_id' => $user['id']]);

        respond(['success' => true, 'message' => 'You have been unregistered']);
    }

    respond([
        'success' => false,
        'message' => 'Unsupported action',
    ], 400);
} catch (Throwable $e) {
    respond([
        'success' => false,
        'message' => 'Event error',
        'error' => $e->getMessage(),
    ], 500);
}
