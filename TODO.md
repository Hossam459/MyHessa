# TODO - Student Attendance API (Group Lessons)

- [x] Step 1: Inspect current attendance authorization and student filtering in `app/Http/Controllers/Attendance/AttendanceController.php`.
- [ ] Step 2: Update `lessonDetails()` to enforce for students:

  - [x] return 403 if student is not an approved member of the lesson’s group.
  - [x] always return only the requesting student’s attendance record (no other students).

- [ ] Step 3: (If needed) Adjust/clean authorization logic to prevent teacher/group leakage.
- [ ] Step 4: Update `AttendanceRequest` / validation only if required by new rules.
- [ ] Step 5: (Optional) Add dedicated endpoint for student attendance across all lessons (if existing endpoints can be used to satisfy the UI).
- [ ] Step 6: Keep API responses consistent for frontend expectations.
- [ ] Step 7: Run `php -l` on modified PHP files.
- [ ] Step 8: Run any available tests (or execute a quick smoke check via routes).

