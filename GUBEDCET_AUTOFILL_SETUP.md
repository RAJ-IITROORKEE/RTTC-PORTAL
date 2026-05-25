## RTTC 2026 - Academics Form GUBEDCET Auto-Fill Setup Guide

---

### **ISSUES FOUND & FIXED**

#### **Issue 1: Missing Database Columns (CRITICAL)** ❌→✅
The PHP form attempts to save 6 fields that don't exist in the database:

**Missing Columns:**
1. `gu_registered` - ENUM('yes','no') - GU registration status
2. `migrated` - ENUM('yes','no') - Migration from GU status  
3. `other_university` - VARCHAR(255) - University name if migrated
4. `gubedcet_name` - VARCHAR(255) - Auto-filled from data.json
5. `gubedcet_category` - VARCHAR(50) - Auto-filled from data.json
6. `academic_declaration` - TINYINT(1) - Declaration checkbox

**Impact:** Form would fail to save data when user submits academics details.

---

### **SOLUTION**

#### **Step 1: Run Database Migration** 
File: `/database/add_gubedcet_fields.sql`

**In phpMyAdmin:**
1. Open phpMyAdmin → Select database `rangiatt_2026`
2. Go to **SQL tab**
3. Copy entire content from `add_gubedcet_fields.sql`
4. Click **Go** to execute

This adds all 6 missing columns with proper types.

---

#### **Step 2: How Auto-Fill Works (Already Implemented)**

**Data Flow:**
```
User enters Roll Number (10 digits)
        ↓
JavaScript validates format
        ↓
Searches in data.json (gubedcet_2026.json)
        ↓
Finds matching "Roll No" entry
        ↓
Auto-fills these read-only fields:
  - gubedcet_name (from "Name")
  - gubedcet_marks (from "Total Marks")
  - gubedcet_rank (from "Rank")
  - gubedcet_category (from "Category")
  - gubedcet_correct (from "Correct Marks")
  - gubedcet_wrong (from "Wrong Marks")
        ↓
User reviews & submits form
        ↓
PHP validates & saves all fields to DB
```

**Current Status:** ✅ All code is in place
- JavaScript auto-fill logic exists (academics.php lines 826-878)
- Form validation exists (academics.php lines 70-94)  
- Save logic exists (academics.php lines 97-132)
- HTML form fields are correct (read-only fields for auto-fill)
- data.json structure matches expectations

---

### **FIELD MAPPING FROM data.json**

| Database Field | JSON Field | Type | Auto-Fill |
|---|---|---|---|
| gubedcet_rollno | Roll No | Text | Manual |
| gubedcet_name | Name | Text | YES ✅ |
| gubedcet_marks | Total Marks | Decimal | YES ✅ |
| gubedcet_rank | Rank | Integer | YES ✅ |
| gubedcet_category | Category | Text | YES ✅ |
| gubedcet_correct | Correct Marks | Integer | YES ✅ |
| gubedcet_wrong | Wrong Marks | Integer | YES ✅ |
| gubedcet_unattempted | (Not in JSON) | Integer | Manual (0) |

---

### **VERIFICATION CHECKLIST**

After running the migration, verify all columns exist:

**In phpMyAdmin SQL tab, run:**

```sql
-- Check GUBEDCET fields
SELECT COLUMN_NAME, COLUMN_TYPE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME='academic_details' 
  AND COLUMN_NAME LIKE 'gubedcet%' 
ORDER BY ORDINAL_POSITION;

-- Check Gauhati University fields
SELECT COLUMN_NAME, COLUMN_TYPE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME='academic_details' 
  AND (COLUMN_NAME LIKE 'gu_%' OR COLUMN_NAME IN ('migrated', 'other_university'))
ORDER BY ORDINAL_POSITION;

-- Check academic_declaration
SELECT COLUMN_NAME, COLUMN_TYPE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME='academic_details' 
  AND COLUMN_NAME = 'academic_declaration';
```

**Expected Result:** All 6 columns should appear in results.

---

### **FILES MODIFIED/CREATED**

| File | Action | Details |
|---|---|---|
| `/database/add_gubedcet_fields.sql` | **Created** | Migration script to add missing columns |
| `/academics.php` | **No changes needed** | Already correctly implemented |
| `/data.json` | **No changes needed** | Already in correct format |

---

### **TESTING FLOW**

1. ✅ Apply database migration
2. ✅ Login to form as student
3. ✅ Navigate to Academics section
4. ✅ Enter a valid 10-digit GUBEDCET roll number from data.json
   - Example: `2525320993`
5. ✅ Tab out or wait 1 second
6. ✅ Auto-fill fields should populate:
   - Candidate Name: `PREETI DEY`
   - Total Marks: `286`
   - Rank: `1`
   - Category: `General`
   - Correct Marks: `296`
   - Wrong Marks: `-10`
7. ✅ Fill remaining required fields
8. ✅ Check declaration checkbox
9. ✅ Click "Save & Continue"
10. ✅ Verify save is successful

---

### **TROUBLESHOOTING**

| Issue | Cause | Solution |
|---|---|---|
| Fields not auto-filling | JSON file not loading | Check browser console for errors; verify `/assets/data/gubedcet_2026.json` exists |
| "Roll number not found" appears | Roll number doesn't exist in data.json | Verify roll number format is exactly 10 digits; check data.json has this roll number |
| Save fails with DB error | Migration not applied | Re-run migration script in phpMyAdmin |
| Readonly fields allow editing | Form security issue | Check for browser extensions blocking readonly; clear cache |

---

### **NOTES**

- All fields in GUBEDCET section are auto-filled and read-only (cannot be manually edited)
- The form prevents saving incomplete declarations
- data.json contains 177,040 student records (4.2MB file)
- Year fields are validated to be between 1990-2027
- Percentage calculations are automatic based on total/obtained marks
