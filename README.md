# Quizora Gradebook (free add-on)

Every creator gets a gradebook: each student's best score on each quiz in one colour-coded grid, class averages, CSV export, and a printable report card they can share with any student.

| Where | What |
|---|---|
| Creator panel → **Gradebook** | Student × quiz grid (best %, tries, pass/close/needs-help colours), class averages, period filter, quiz picker, student search, CSV export |
| Click a student | Every attempt (date, time taken, score, certificate link) and the **report card** controls |
| Report card | `/quizora-gradebook/report/{token}`: a private, printable page (A4, Save as PDF) with the student's best result per quiz, an optional note, and certificate verification links. The creator can renew or turn off the link at any time. |
| Admin panel → Configuration → **Gradebook** | Active creators in the last 30 days, students, attempts, average score, report cards shared and opened |

Needs Quizora 1.5+. Free for every creator (`plan_feature: false`).

## How it works

- Grades are read live from Quizora's attempts. Only finished attempts count (completed or timed out, with a score). Nothing is copied, so the gradebook is always current and uninstalling loses nothing.
- Its only table, `quizora_gradebook_report_cards`, holds report-card links: creator, student, 40-character token, note, view count.
- Every query starts from the signed-in creator's own quizzes (`Support\Gradebook`), so ids sent from the browser can never reach another creator's students or quizzes.
- Report cards are `noindex`, rate-limited, and only list the issuing creator's quizzes.
- The CSV export neutralises spreadsheet formulas in names and titles.

## Develop

```bash
# Quizora .env: LICENTRA_MODULES_DEV=true, then php artisan config:clear
ln -s ~/Sites/gitlab/licentra/modules/quizora-gradebook ~/Sites/gitlab/Quizora/modules-dev/quizora-gradebook
cd ~/Sites/gitlab/Quizora
php artisan module:migrate quizora-gradebook     # table + publishes public/*.css
php artisan test ../licentra/modules/quizora-gradebook/tests/GradebookModuleTest.php
```

After changing a file in `public/`, run `module:migrate` again to republish it.

## Release

```bash
mkdir -p ~/Releases && cd ~/Releases
php ~/Sites/gitlab/Quizora/vendor/pacific/licentra-client/bin/licentra-release module ~/Sites/gitlab/licentra/modules/quizora-gradebook --upload
```

Then publish it in Licentra: **Admin → Releases**. The first release also needs the add-on product: **Admin → Products → New**, slug `quizora-gradebook`, *Add-on for* Quizora, pricing *Free*. The Licentra **Team Handbook** has the full steps.
