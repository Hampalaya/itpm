<?php
function logAudit($pdo, $action, $table, $recordId = null, $description = '') {
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'] ?? null, $action, $table, $recordId, $description, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
}

function encoderStudentScopeSql(string $studentAlias, array &$params): string {
    if (($_SESSION['role'] ?? '') !== 'encoder') {
        return '';
    }

    if (empty($_SESSION['assigned_section']) || empty($_SESSION['assigned_grade'])) {
        return ' AND 1=0';
    }

    $params[] = $_SESSION['assigned_section'];
    $params[] = $_SESSION['assigned_grade'];

    return " AND {$studentAlias}.section = ? AND {$studentAlias}.grade_level = ?";
}

function addEncoderStudentScope(array &$where, array &$params, string $studentAlias = 's'): void {
    if (($_SESSION['role'] ?? '') !== 'encoder') {
        return;
    }

    if (empty($_SESSION['assigned_section']) || empty($_SESSION['assigned_grade'])) {
        $where[] = '1=0';
        return;
    }

    $prefix = $studentAlias !== '' ? "{$studentAlias}." : '';
    $where[] = "{$prefix}section = ?";
    $params[] = $_SESSION['assigned_section'];
    $where[] = "{$prefix}grade_level = ?";
    $params[] = $_SESSION['assigned_grade'];
}

function canAccessStudent(PDO $pdo, int $studentId): bool {
    if ($studentId <= 0) {
        return false;
    }

    $params = [$studentId];
    $sql = "SELECT COUNT(*) FROM students s WHERE s.id = ?" . encoderStudentScopeSql('s', $params);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn() > 0;
}

function canAccessMeasurement(PDO $pdo, int $measurementId): bool {
    if ($measurementId <= 0) {
        return false;
    }

    $params = [$measurementId];
    $sql = "
        SELECT COUNT(*)
        FROM measurements m
        JOIN students s ON m.student_id = s.id
        WHERE m.id = ?
    " . encoderStudentScopeSql('s', $params);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn() > 0;
}
?>
