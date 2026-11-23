SEASONVERSE ADMIN BACKEND UPDATE

Included files:
- admin-bootstrap.php (new shared admin authentication + DB connection helper)
- admin-login.php
- admin-dashboard.php
- admin-users.php
- admin-content.php
- admin-ai.php
- admin-analytics.php
- admin-reviews.php
- admin-supports.php
- admin-settings.php
- user-signup.php, user-login.php, user-experiences.php (integration changes)

INSTALLATION
1. BACK UP your current SeasonVerse2 folder and database first.
2. Copy all included PHP files into C:\xampp\htdocs\SeasonVerse2\ and overwrite when prompted.
3. Keep your existing CSS files and images. The page design files were not redesigned.
4. Start Apache and MySQL, then test admin login and all modules.
5. Database name for normal application/admin pages is seasonverse_db. The AI page currently stores chat history in the legacy seasonverse database; the admin helper reads that database separately so existing AI chat records remain visible.

IMPORTANT SECURITY NOTE
The original admin-login.php creates a default local admin if it does not exist:
admin@seasonverse.com / admin123
Change this password immediately after first login. Do not deploy this default credential publicly.

FUNCTIONAL NOTES
- User Management now reads the real users table, adds a status column if needed, and creates actual user accounts using the password entered in the Add User form.
- Inactive users are prevented from signing in by user-login.php.
- Admin Content no longer bypasses admin authentication and no longer inserts fake starter content.
- Dashboard and analytics counts are based on existing database records. Session duration is shown as Not tracked because the app does not record session-duration data.
- AI model choice is persisted as an admin setting, but it does not switch the Groq model used in user-ai.php; the UI choices are labels, not actual Groq model IDs.
- Reviews are based on real community_experiences records. Moderation status is stored there, and the public Experience page shows only PUBLISHED stories.
- Support tickets have Open / In Progress / Resolved / Closed status actions.
- Platform settings are saved in admin_settings; the registration toggle is enforced by user-signup.php.

VALIDATION
All included PHP files passed PHP 8.4 syntax checks. Database/runtime testing must still be completed in your own XAMPP environment because no live SeasonVerse MySQL database was available during this update.
