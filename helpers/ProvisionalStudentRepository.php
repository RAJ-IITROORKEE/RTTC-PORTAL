<?php
/**
 * Read-only access to the current GUBEDCET provisional result CSV.
 */
if (!defined('APP_INIT')) die('Direct access not permitted');

class ProvisionalStudentRepository
{
    private const COLUMNS = [
        'Sl. No.' => 'serial_no',
        'RollNo' => 'roll_no',
        'Name' => 'name',
        'Gender' => 'gender',
        'Category' => 'category',
        'QBookletSeries' => 'booklet_series',
        'Correct Marks' => 'correct_marks',
        'Wrong Marks' => 'wrong_marks',
        'Total Marks' => 'total_marks',
        'Rank' => 'rank',
    ];

    private string $csvPath;

    public function __construct(string $csvPath)
    {
        $this->csvPath = $csvPath;
    }

    public function findByRollNo(string $rollNo): ?array
    {
        $rollNo = trim($rollNo);
        if (!preg_match('/^\d{10}$/', $rollNo)) {
            return null;
        }

        [$handle, $columnMap] = $this->openCsv();
        try {
            while (($values = fgetcsv($handle)) !== false) {
                $student = $this->mapRow($values, $columnMap);
                if ($student !== null && $student['roll_no'] === $rollNo) {
                    return $student;
                }
            }
        } finally {
            fclose($handle);
        }

        return null;
    }

    /**
     * Return a filtered page plus whole-dataset statistics and filter options.
     */
    public function browse(
        string $search = '',
        string $gender = '',
        string $category = '',
        int $page = 1,
        int $perPage = 10
    ): array {
        $search = trim($search);
        $gender = trim($gender);
        $category = trim($category);
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;

        $rows = [];
        $matchingTotal = 0;
        $totalStudents = 0;
        $marksTotal = 0.0;
        $marksCount = 0;
        $highestMarks = null;
        $lowestMarks = null;
        $genderCounts = [];
        $categoryCounts = [];

        [$handle, $columnMap] = $this->openCsv();
        try {
            while (($values = fgetcsv($handle)) !== false) {
                $student = $this->mapRow($values, $columnMap);
                if ($student === null) {
                    continue;
                }

                $totalStudents++;
                $studentGender = $student['gender'] !== '' ? $student['gender'] : 'NOT SPECIFIED';
                $studentCategory = $student['category'] !== '' ? $student['category'] : 'NOT SPECIFIED';
                $genderCounts[$studentGender] = ($genderCounts[$studentGender] ?? 0) + 1;
                $categoryCounts[$studentCategory] = ($categoryCounts[$studentCategory] ?? 0) + 1;

                if (is_numeric($student['total_marks'])) {
                    $marks = (float) $student['total_marks'];
                    $marksTotal += $marks;
                    $marksCount++;
                    $highestMarks = $highestMarks === null ? $marks : max($highestMarks, $marks);
                    $lowestMarks = $lowestMarks === null ? $marks : min($lowestMarks, $marks);
                }

                if (!$this->matches($student, $search, $gender, $category)) {
                    continue;
                }

                if ($matchingTotal >= $offset && count($rows) < $perPage) {
                    $rows[] = $student;
                }
                $matchingTotal++;
            }
        } finally {
            fclose($handle);
        }

        ksort($genderCounts, SORT_NATURAL | SORT_FLAG_CASE);
        ksort($categoryCounts, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'rows' => $rows,
            'total' => $matchingTotal,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int) ceil($matchingTotal / $perPage)),
            'stats' => [
                'total_students' => $totalStudents,
                'average_marks' => $marksCount > 0 ? round($marksTotal / $marksCount, 2) : null,
                'highest_marks' => $highestMarks,
                'lowest_marks' => $lowestMarks,
                'gender' => $genderCounts,
                'category' => $categoryCounts,
            ],
            'filters' => [
                'genders' => array_keys($genderCounts),
                'categories' => array_keys($categoryCounts),
            ],
        ];
    }

    private function matches(array $student, string $search, string $gender, string $category): bool
    {
        if ($gender !== '' && strcasecmp($student['gender'], $gender) !== 0) {
            return false;
        }
        if ($category !== '' && strcasecmp($student['category'], $category) !== 0) {
            return false;
        }
        if ($search === '') {
            return true;
        }

        foreach ($student as $value) {
            if (stripos((string) $value, $search) !== false) {
                return true;
            }
        }
        return false;
    }

    private function openCsv(): array
    {
        if (!is_file($this->csvPath) || !is_readable($this->csvPath)) {
            throw new RuntimeException('The provisional student data file is unavailable.');
        }

        $handle = fopen($this->csvPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('The provisional student data file could not be opened.');
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            throw new RuntimeException('The provisional student data file is empty.');
        }

        $columnMap = [];
        foreach ($headers as $index => $header) {
            $header = trim((string) $header);
            if ($index === 0) {
                $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
            }
            if (isset(self::COLUMNS[$header])) {
                $columnMap[$index] = self::COLUMNS[$header];
            }
        }

        if (count($columnMap) !== count(self::COLUMNS)) {
            fclose($handle);
            throw new RuntimeException('The provisional student data file has an invalid header.');
        }

        return [$handle, $columnMap];
    }

    private function mapRow(array $values, array $columnMap): ?array
    {
        $student = array_fill_keys(array_values(self::COLUMNS), '');
        foreach ($columnMap as $index => $field) {
            $student[$field] = trim((string) ($values[$index] ?? ''));
        }

        return $student['roll_no'] === '' ? null : $student;
    }
}
