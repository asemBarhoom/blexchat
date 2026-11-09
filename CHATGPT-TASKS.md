# Tasks for ChatGPT: Blexchat admin panel (A-1, then A-4)

You are working on **Blexchat**, a Laravel 13 / PHP 8.3 backend with a Filament v5 admin panel.
Repository: https://github.com/asemBarhoom/blexchat

Do **only** the two tasks below, in this order, and nothing else.

---

## Hard rules (read first; breaking any of them means the work is rejected)

1. **Never commit to `master`. Never merge. Never push to `master`.** Each task has its own branch (named below). Commit only there. The owner reviews and merges it.
2. **Never run** `db:seed` against a real database, `migrate:fresh`, `migrate:reset`, or anything that wipes data. Tests use SQLite in memory (already set in `phpunit.xml`), so running them is safe.
3. **No new dependencies.** Do not change `composer.json`, `composer.lock`, `package.json` or `package-lock.json`.
4. **No secrets.** Never write keys, tokens or passwords into code, config, `.env` or tests.
5. **No migrations.** Both tasks need only existing columns.
6. **Touch only the files listed for each task**, plus its test file. Do not reformat, rename or "clean up" anything else.
7. Do not edit `CLAUDE.md`, `.ai/`, `routes/`, `config/`, `database/migrations/`, `BACKEND-TASKS.md`, `FRONTEND-TASKS.md` or `DESIGN-TASKS.md`.
8. Do not regenerate the Swagger / OpenAPI docs.
9. Follow `CLAUDE.md` in the repo root, especially:
   - Use curly braces for every `if`, even one-line bodies.
   - Give every method an explicit return type.
   - Admin labels are in **Arabic**, matching the sibling fields in the same file.
   - Read the neighbouring code before writing, and copy its style.
10. Before every commit, run both of these and make sure they pass:
    ```
    vendor/bin/pint --dirty --format agent
    php artisan test --compact tests/Feature/AdminPanelTest.php
    ```
    Also run your new test file. If a test that was passing before now fails, fix your change; never edit or delete an existing test to make it pass.
11. If something is unclear, or a task seems to need more than the listed files, **stop and write down the question** instead of guessing.

---

## Setup

```
git fetch origin
git checkout -b feat/admin-plan-team-fields origin/master   # for A-1
composer install
cp .env.example .env && php artisan key:generate              # only if .env is missing
php artisan test --compact tests/Feature/AdminPanelTest.php   # must pass before you change anything
```

If the branch already exists on the remote, use `git checkout feat/admin-plan-team-fields` instead of `-b`.

---

## Task A-1: Plans, team seats and conversation assignment

**Branch:** `feat/admin-plan-team-fields`

**Why:** The `plans` table already has `teammates_limit` (`unsigned int`, `NOT NULL`, default `1`) and `has_assignment` (`bool`, default `false`). Both are fillable and cast in `app/Models/Plan.php`. But the admin cannot see or change them; today they are only set by a migration. Every plan has at least one seat — the owner — so the field is required and can never be empty.

**Files to change:**
- `app/Filament/Resources/PlanResource.php`
- New test: `tests/Feature/PlanTeamFieldsAdminTest.php` (create it with `php artisan make:test --pest PlanTeamFieldsAdminTest --no-interaction`)

**What to do in `PlanResource.php`:**
1. In the `Section::make('السعر والحدود')` schema, add after `live_automations_limit`:
   ```php
   TextInput::make('teammates_limit')->label('عدد المقاعد (مع المالك)')->numeric()->integer()->minValue(1)->required()
       ->helperText('المالك يحسب مقعداً.'),
   ```
2. In the `Grid::make(4)` toggles at the bottom, add `Toggle::make('has_assignment')->label('يشمل توزيع المحادثات')`. Change `Grid::make(4)` to `Grid::make(5)` so it still fits on one row.
3. In `table()`, add after the `contacts_limit` column:
   ```php
   TextColumn::make('teammates_limit')->label('المقاعد')->numeric(),
   IconColumn::make('has_assignment')->label('التوزيع')->boolean(),
   ```

**Test (`tests/Feature/PlanTeamFieldsAdminTest.php`):** Copy the setup from the top of `tests/Feature/AdminPanelTest.php`: `RefreshDatabase`, seed `DatabaseSeeder`, and act as `admin@blexchat.test`. Then:
- **It shows the new fields:** `get('/admin/plans')` returns OK and shows `المقاعد`.
- **The admin can save them:** use Livewire on `App\Filament\Resources\PlanResource\Pages\ManagePlans` to call the table's `EditAction` on the `pro` plan with `teammates_limit` = 5 and `has_assignment` = true. Then assert the database has those values.
  - For the Filament v5 action testing API, look at how `tests/Feature/CheckoutAdminTest.php` and `tests/Feature/AdminPanelTest.php` call actions (`TestAction`, `callAction`), and do the same.

**Done when:** pint is clean, the new test and `AdminPanelTest.php` pass, and there is one commit on the branch:
`feat: edit team seats and conversation assignment on plans in the admin panel`

---

## Task A-4: Automations, the trash, the AI switch and the folder

Start this only after A-1 is committed. Start from master again, not from the A-1 branch:
```
git checkout -b feat/admin-automations-trash origin/master
```

**Branch:** `feat/admin-automations-trash`

**Why:**
- `app/Models/Automation.php` uses `SoftDeletes`. Deleted automations stay in a trash for `Automation::TRASH_DAYS` (30) days, then get pruned. The admin panel cannot show trashed automations or restore one, so support cannot undo a customer's mistake.
- The panel also does not show `ai_enabled` (bool) or the folder (relation `folder()`, a `BelongsTo` to `AutomationFolder`).

**Files to change:**
- `app/Filament/Resources/AutomationResource.php`
- `app/Filament/Resources/AutomationResource/Pages/ViewAutomation.php`, only if the view page has to load trashed records.
- New test: `tests/Feature/AutomationTrashAdminTest.php`

**What to do:**
1. Table columns: add `IconColumn::make('ai_enabled')->label('AI')->boolean()`, and `TextColumn::make('folder.name')->label('المجلد')->placeholder('—')->toggleable()`. Check `app/Models/AutomationFolder.php` for the real name column before using `folder.name`.
2. Filters: add `TrashedFilter::make()` (`Filament\Tables\Filters\TrashedFilter`), labelled `المحذوفة`.
3. Record actions:
   - Add `RestoreAction::make()->label('استرجاع')` (`Filament\Actions\RestoreAction`).
   - Keep the existing `DeleteAction`, and give it `->modalDescription('تنتقل الأتمتة إلى سلة المحذوفات، وتُحذف نهائياً بعد 30 يوماً.')`.
   - **Do not add** `ForceDeleteAction` or `ForceDeleteBulkAction`. Final deletion stays automatic (pruning).
4. Let the resource see trashed records by overriding `getRecordRouteBindingEloquentQuery()` to remove the `SoftDeletingScope`. This is the standard Filament pattern for soft-deleting resources; check the Filament v5 docs for the exact code.
5. Do not change the existing `toggle` action, or the rule that the panel cannot create or edit automations (`AdminPanelTest.php` tests that rule).

**Test (`tests/Feature/AutomationTrashAdminTest.php`):** Same setup as A-1. Then:
- **A deleted automation can be restored:**
  1. Create an automation with `Automation::factory()`, then `->delete()` it.
  2. Use Livewire on `App\Filament\Resources\AutomationResource\Pages\ListAutomations` with the trashed filter set to "only trashed", and assert the record can be seen.
  3. Call the restore action on it.
  4. Assert that `deleted_at` is null again.
- **The list still renders:** `get('/admin/automations')` returns OK and shows `المجلد`.

**Done when:** pint is clean, the new test and `AdminPanelTest.php` pass, and there is one commit on the branch:
`feat: restore deleted automations and show the AI switch and folder in the admin panel`

---

## When you finish

Push only the two branches:
```
git push origin feat/admin-plan-team-fields
git push origin feat/admin-automations-trash
```
Do **not** open a merge into master yourself. Then report:
- The commits on each branch.
- The test output.
- Anything you skipped, or any question you had.

**Out of scope, do not start these:** the other admin cards A-2 (users and team deletion), A-3 (conversations) and A-5 (dashboard), and every other task on the Trello board.
