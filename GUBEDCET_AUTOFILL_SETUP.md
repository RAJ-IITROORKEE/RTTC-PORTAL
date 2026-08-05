## GUBEDCET 2026 Final Merit List

The application uses `final_list/GUBEDCET 2026 FINAL LIST.csv` as the only runtime source for:

- Authenticated roll-number autofill on `academics.php`
- Server-side validation before academic details are saved
- The read-only admin Final Merit List and its statistics

### Source and Conversion

The official source workbooks are retained in `final_list/`:

- `gubedcet-2026-final-merit-list-1_20260805000125-1-299.xlsx`
- `gubedcet-2026-final-merit-list-1_20260805000125-300-505.xlsx`

Regenerate the CSV only from those workbooks:

```powershell
php tools/build_final_merit_list.php
```

The command stops without replacing the CSV if the two workbooks do not have the expected ten columns, serial numbers are not continuous, a roll number is duplicated or malformed, a result field is invalid, or the rejected-result records are malformed.

### Field Contract

| CSV column | Stored field |
|---|---|
| `RollNo` | `gubedcet_rollno` |
| `Name` | `gubedcet_name` |
| `Gender` | `gubedcet_gender` |
| `Category` | `gubedcet_category` |
| `QBookletSeries` | `gubedcet_booklet_series` |
| `Correct Marks` | `gubedcet_correct` |
| `Wrong Marks` | `gubedcet_wrong` |
| `Total Marks` | `gubedcet_marks` |
| `Rank` | `gubedcet_rank` |

Two official records are marked `REJECTED DUE TO NON COMPLIANCE WITH RULES` and have no marks or rank. They remain visible in the admin final list, are excluded from mark calculations, and are blocked from academic submission.

Submitted academic records are historical data and are not rewritten when the final merit list changes.

### Verification

```powershell
php tests/final_merit_list_build_test.php
php tests/final_merit_list_repository_test.php
php tests/final_merit_list_csv_smoke.php
```
