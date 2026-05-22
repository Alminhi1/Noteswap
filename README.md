# NoteSwap

NoteSwap is a PHP and MySQL web application for students to upload, browse, save, and share study notes. It is designed as a class notes portal where users can create accounts, upload course material, find notes by section/semester/subject, message classmates, and manage their own uploaded resources.

## Features

- User registration, login, logout, and password reset flow
- Student dashboard with notes filtered by section, semester, and subject
- Upload notes with PDF, Word document, and image attachments
- View note details, files, views, comments, and uploader information
- Save favorite notes and pin notes for quick access
- User profiles with uploaded notes, account details, and contribution stats
- Friend requests and private messaging between users
- Admin login and admin dashboard for managing users, notes, files, comments, and platform statistics
- Responsive UI with light/dark theme support

## Technologies Used

- PHP
- MySQL
- PDO for database access
- HTML5
- CSS3
- JavaScript
- PHP sessions for authentication
- File upload handling for PDFs, documents, and images
- SMTP/PHP mail support for password reset emails

## Project Structure

```text
.
|-- css/
|   `-- style.css
|-- images/
|-- uploads/
|-- db.php
|-- index.php
|-- register.php
|-- login.php
|-- dashboard.php
|-- upload.php
|-- view_note.php
|-- my_notes.php
|-- favorites.php
|-- messages.php
|-- profile.php
|-- admin.php
`-- README.md
```

## Requirements

- PHP 8.0 or newer
- MySQL or MariaDB
- A local server environment such as XAMPP, WAMP, Laragon, or MAMP
- A web browser

## How to Run Locally

1. Clone the repository:

   ```bash
   git clone https://github.com/your-username/noteswap.git
   ```

2. Move the project folder into your local server directory.

   For XAMPP on Windows, place it inside:

   ```text
   C:\xampp\htdocs\
   ```

3. Start Apache and MySQL from your local server control panel.

4. Create a MySQL database for the project, for example:

   ```sql
   CREATE DATABASE noteswap;
   ```

5. Import or create the required database tables.

   The application expects tables such as:

   - `users`
   - `notes`
   - `note_files`
   - `comments`
   - `curriculum`
   - `chat_messages`
   - `friend_requests`
   - `note_user_bookmarks`
   - `password_resets`
   - `manual_top_contributors`

6. Update the database connection in `db.php`:

   ```php
   $host = "localhost";
   $dbname = "noteswap";
   $username = "root";
   $password = "";
   ```

7. Make sure the `uploads/` folder exists and is writable by the server.

8. Open the project in your browser:

   ```text
   http://localhost/noteswap/
   ```

## Email Configuration

Password reset emails are configured in `mail_config.php`. For local testing, update the SMTP settings with your own email service credentials.

Do not commit real passwords, SMTP app passwords, or production database credentials to a public GitHub repository. Use environment variables or a private config file for production deployments.

## Main Pages

- `index.php` - Landing page
- `register.php` - Create a new user account
- `login.php` - User login
- `dashboard.php` - Browse and search notes
- `upload.php` - Upload notes and files
- `view_note.php` - View a note and its files
- `favorites.php` - Saved notes
- `messages.php` - Friend requests and private messages
- `profile.php` - User profile and account settings
- `admin_login.php` - Admin login
- `admin.php` - Admin dashboard

## Future Improvements

- Add a database schema file for easier setup
- Move sensitive credentials into environment variables
- Add role-based permissions for admin actions
- Add automated tests for authentication, uploads, and messaging
- Improve file validation and add virus scanning for uploads

## Author

Created as a student notes-sharing platform project.
