# Patient Management System

A hospital management system I built using PHP, SQLite, and vanilla JavaScript. It handles everything from patient records to appointment scheduling, billing, and staff management.

## What This Does

Basically, it's a complete system for managing a hospital or clinic. You can track patients, schedule appointments, manage medical records, handle billing, schedule staff, and control who can access what based on their role. I tried to make it as comprehensive as possible while keeping it simple to use.

## Features

### Patient Management
You can create, view, edit, and delete patient records with all the important stuff - name, date of birth, gender, address, emergency contacts, insurance info, blood type, allergies, and medical history. There's real-time search by name, phone, or email, and you can filter by gender or sort by different fields. The critical stuff like blood type and allergies are shown prominently so they don't get missed.

### Role-Based Access Control (RBAC)
I set up 7 different user roles: Administrator, Receptionist, Doctor, Nurse, Pharmacist, Lab Technician, and Billing Officer. Each role has specific permissions - for example, doctors and nurses only see their assigned patients, and receptionists can't access medical records. The UI automatically hides buttons and navigation items you don't have permission for, and all the API endpoints check permissions on the server side too. Everything gets logged in an audit trail.

### User Authentication
Standard login/logout system with secure password hashing. Admins can create and manage users with different roles. The default admin login is `admin@hospital.com` with password `admin123` (obviously change this in production!).

### Doctor Management
Full doctor profiles with specialties. You can search by name or specialty, and link doctors to user accounts so they can be scheduled.

### Appointment Scheduling
This was one of the trickier parts. The system checks for conflicts automatically - it won't let you double-book a doctor or a room. You can assign both doctors and rooms to appointments, and the room status updates automatically when appointments are created or cancelled. There's date range filtering, and you can track the full appointment lifecycle from scheduled to completed or cancelled, including diagnosis, treatment, and prescriptions.

### Room Management
Create and manage rooms with different types (Examination, Operation, Consultation, Emergency, ICU, Ward, Imaging, Lab, Pharmacy, Therapy, Administrative). The system tracks availability automatically based on appointments, and you can search by number, name, type, or availability status. Capacity is tracked too.

### Staff Scheduling
Create and manage staff schedules with shift templates (Morning, Afternoon, Night, Day, Extended). The system prevents double-booking staff, and there's a leave request system where staff can request time off and managers can approve or reject it. You can view schedules in a list format with filters and date ranges.

### Medical Records
Store digital medical records with file upload support. Records are organized by type (diagnosis, treatment, prescription, lab results, imaging) and can be linked to specific patients and appointments. You can upload PDFs, images, Word docs, Excel files, and text files. Search and filter by patient, type, or content.

### Billing & Payments
Create and manage patient bills, track payment status (pending, paid, cancelled), record payment methods, and set due dates. There's revenue analytics to see total revenue and pending payments, plus financial reports.

### M-Pesa Mobile Payments
Integrated Safaricom Daraja support lets you send STK Push requests directly to patient phones, verify payments automatically, log every M-Pesa interaction, and store receipt numbers alongside billing records. Built-in rate limiting and retry handling make the flow resilient for both sandbox and production environments.

### Reporting & Analytics
Dashboard with real-time stats and KPIs. Patient demographics (gender distribution, age groups, monthly registrations), appointment analytics (status distribution, doctor workload, daily trends), revenue analysis (by status, monthly trends, payment methods), and you can export data to CSV for external analysis.

### UI/UX
I tried to make it look modern and clean. It's fully responsive so it works on desktop, tablet, and mobile. The interface uses smooth animations, intuitive tabbed navigation, and real-time updates without page refreshes. There are success/error messages and loading states for user feedback. I used Font Awesome icons throughout, and built a custom modal system for forms and actions. Each section has unique, descriptive subtitles that clearly explain the functionality, making the system more intuitive and professional.

## Technical Stuff

### Backend
- PHP 8.4+ with proper error handling
- SQLite database (file-based, so no separate database server needed)
- RESTful API endpoints for all operations
- Secure session management
- File upload support for medical documents
- CORS enabled for cross-origin requests
- Complete audit logging
- Safaricom Daraja (M-Pesa) integration with STK Push initiation, callbacks, and transaction logging

### Frontend
- Vanilla JavaScript (no framework dependencies - I wanted to keep it simple)
- Modern CSS with Grid, Flexbox, and custom properties
- Mobile-first responsive design
- Progressive enhancement (works without JavaScript, though with limited functionality)
- Font Awesome for icons

### Database
The database includes tables for users, patients, doctors, appointments, medical records, billing, rooms, staff schedules, leave requests, shift templates, and an audit trail.

## Getting Started

### What You Need
- PHP 8.4 or higher
- SQLite extension enabled (usually comes with PHP)
- A web server (Apache/Nginx) or just use PHP's built-in server

### Setup Steps

1. **Get the files** - Clone or download the project

2. **Initialize the database:**
   ```bash
   cd backend
   php init_db.php
   ```

3. **Set up RBAC (optional but recommended):**
   The RBAC tables should be created by `init_db.php`, but if you need to add them separately, check the `backend/setup_scheduling_tables.php` file or look for similar setup scripts.

4. **Set up staff scheduling (optional):**
   Run `php setup_scheduling_tables.php` in the backend directory if you want the scheduling features.

5. **Add sample data (optional):**
   ```bash
   php add_sample_data.php
   php add_rbac_users.php
   php add_rooms.php
   ```
   This will give you some test data to work with.

6. **Configure environment variables:**
   - `DB_PATH` (optional) to point to a custom SQLite file location
   - `MPESA_*` keys for sandbox and/or production as documented in `docs/MPESA_INTEGRATION.md`
   - Any additional secrets (kept outside version control)
   > Tip: On Render or any container platform, set these via the provider's environment settings rather than committing them.

7. **Start the server:**
   
   On Windows (PowerShell):
   ```powershell
   .\start-server.ps1
   ```
   
   Or manually:
   ```bash
   php -S localhost:8000 router.php
   ```

   Using Docker (requires Docker Desktop):
   ```bash
   docker build -t hospital-management .
   docker run -p 10000:10000 --env-file .env hospital-management
   ```
   The container honors `PORT` and the same `MPESA_*`/`DB_PATH` environment variables.

8. **Open in your browser:**
   ```
   http://localhost:8000/
   ```

### Default Login
- **Email**: `admin@hospital.com`
- **Password**: `admin123`

**Please change this immediately in a production environment!**

## Project Structure

```
patient-management/
├── backend/
│   ├── api/                    # All the API endpoints
│   │   ├── auth.php            # Login/logout
│   │   ├── patients.php        # Patient CRUD
│   │   ├── doctors.php         # Doctor management
│   │   ├── appointments.php    # Scheduling
│   │   ├── medical_records.php # Medical records
│   │   ├── billing.php         # Billing
│   │   ├── reports.php         # Analytics
│   │   ├── rooms.php           # Room management
│   │   ├── schedules.php       # Staff scheduling
│   │   ├── users.php           # User management
│   │   ├── permissions.php     # RBAC checks
│   │   └── audit.php           # Audit logging
│   ├── data/
│   │   └── database.sqlite     # The database file
│   ├── uploads/
│   │   └── medical_records/    # Uploaded documents
│   ├── config.php              # Database config
│   ├── db.php                  # Database helper
│   ├── cors.php                # CORS setup
│   ├── init_db.php             # Database initialization
│   ├── add_sample_data.php     # Sample data
│   ├── add_rbac_users.php      # Test users
│   └── add_rooms.php           # Sample rooms
├── frontend/
│   ├── css/
│   │   └── styles.css          # All the styling
│   ├── js/
│   │   ├── app.js              # Main app logic
│   │   ├── rbac-permissions.js # Permission config
│   │   ├── scheduling.js       # Scheduling features
│   │   └── enhancements.js     # UI improvements
│   └── index.html              # Main page
├── router.php                  # PHP server router
├── start-server.ps1           # Windows startup script
└── README.md                   # This file
```

## API Endpoints

All the API endpoints are in `backend/api/`. Here's a quick overview:

### Authentication (`/backend/api/auth.php`)
- `POST` - Login: `{action: 'login', email, password}`
- `POST` - Register: `{action: 'register', name, email, password, role}`
- `GET` - Get current user: `?action=me`
- `GET` - Logout: `?action=logout`

### Patients (`/backend/api/patients.php`)
- `GET` - List: `?search=term&gender=Male&sort=name&order=ASC`
- `GET` - Get one: `?id=123`
- `POST` - Create: `{first_name, last_name, dob, gender, ...}`
- `PUT` - Update: `?id=123` with patient data
- `DELETE` - Delete: `?id=123`

### Doctors (`/backend/api/doctors.php`)
- `GET` - List: `?search=term&specialty=Cardiology`
- `GET` - Get one: `?id=123`
- `POST` - Create: `{first_name, last_name, specialty, ...}`
- `PUT` - Update: `?id=123` with doctor data
- `DELETE` - Delete: `?id=123`

### Appointments (`/backend/api/appointments.php`)
- `GET` - List: `?patient_id=123&doctor_id=456&status=scheduled&date_from=2024-01-01&date_to=2024-12-31`
- `GET` - Get one: `?id=123`
- `GET` - Check availability: `?action=check_availability&date=2024-11-11&start_time=08:00&end_time=18:00`
- `POST` - Create: `{patient_id, doctor_id, room_id, start_time, end_time, reason, ...}`
- `PUT` - Update: `?id=123` with appointment data
- `DELETE` - Cancel: `?id=123`

### Medical Records (`/backend/api/medical_records.php`)
- `GET` - List: `?patient_id=123&record_type=diagnosis&search=term`
- `GET` - Get one: `?id=123`
- `POST` - Create: `{patient_id, record_type, title, content, file}`
- `PUT` - Update: `?id=123` with record data
- `DELETE` - Delete: `?id=123`

### Billing (`/backend/api/billing.php`)
- `GET` - List: `?patient_id=123&status=pending&date_from=2024-01-01&date_to=2024-12-31`
- `GET` - Get one: `?id=123`
- `POST` - Create: `{patient_id, amount, status, due_date, ...}`
- `PUT` - Update: `?id=123` with bill data
- `DELETE` - Delete: `?id=123`

### Rooms (`/backend/api/rooms.php`)
- `GET` - List: `?room_type=Examination&is_available=1&search=term`
- `GET` - Get one: `?id=123`
- `POST` - Create: `{room_number, room_name, room_type, capacity, is_available, notes}`
- `PUT` - Update: `?id=123` with room data
- `DELETE` - Delete: `?id=123`

### Staff Scheduling (`/backend/api/schedules.php`)
- `GET` - List: `?user_id=123&date_from=2024-01-01&date_to=2024-12-31`
- `GET` - Get one: `?id=123`
- `GET` - Shift templates: `?action=templates`
- `GET` - Leave requests: `?action=leave_requests&status=pending`
- `POST` - Create schedule: `{user_id, shift_template_id, schedule_date, start_time, end_time, ...}`
- `POST` - Leave request: `{action: 'leave_request', user_id, leave_type, start_date, end_date, reason}`
- `PUT` - Update: `?id=123` with schedule data
- `PUT` - Approve/reject leave: `?id=123&action=approve_leave` with `{status, rejection_reason}`
- `DELETE` - Cancel: `?id=123`

### Users (`/backend/api/users.php`)
- `GET` - List: `?role=doctor&search=term`
- `GET` - Get one: `?id=123`
- `POST` - Create: `{name, email, password, role, ...}`
- `PUT` - Update: `?id=123` with user data
- `DELETE` - Delete: `?id=123`

### Reports (`/backend/api/reports.php`)
- `GET` - Dashboard: `?type=dashboard`
- `GET` - Patient demographics: `?type=patients&date_from=2024-01-01&date_to=2024-12-31`
- `GET` - Appointment analytics: `?type=appointments&date_from=2024-01-01&date_to=2024-12-31`
- `GET` - Revenue: `?type=revenue&date_from=2024-01-01&date_to=2024-12-31`
- `GET` - Export: `?type=export&table=patients&format=csv`

## How Things Work

### Dashboard
Shows real-time stats and quick access to everything. Different roles see different things based on their permissions.

### Patient Management
Full patient profiles with search and filtering. Medical history, emergency contacts, and critical info like blood type and allergies are easy to find.

### Appointment Scheduling
The system automatically checks for conflicts and won't let you double-book. It checks doctor and room availability, and updates room status automatically.

### Room Management
Rooms are tracked automatically based on appointments. You can search and filter by type, availability, and capacity.

### Staff Scheduling
Create shifts, manage leave requests, and prevent conflicts. Shift templates make it quick to schedule common patterns.

### Medical Records
Digital storage with file uploads. Everything is organized by type and linked to patients and appointments.

### Billing
Create invoices, track payments, and generate revenue reports. Due dates are managed automatically.

### Reporting
Dashboards, demographics, appointment analytics, revenue tracking, and CSV exports for external analysis.

## Security

I tried to follow best practices:
- Passwords are hashed using PHP's `password_hash()`
- Secure session management with expiration
- Server-side validation on all inputs
- Prepared statements everywhere to prevent SQL injection
- HTML escaping to prevent XSS
- File upload validation (type and size limits)
- Role-based access control on both frontend and backend
- Complete audit trail of all actions
- Proper CORS configuration

## User Roles & Permissions

### Administrator
Can do everything - full system access, user management, all CRUD operations, system configuration.

### Receptionist
Can register patients, schedule appointments, create billing records, view doctors and rooms, and view schedules. Can't access medical records or reports.

### Doctor
Can view only their assigned patients, add diagnoses and treatment plans, create prescriptions, order lab tests, and update appointment notes. Can't register patients or see billing.

### Nurse
Can view all patients, record vital signs, update medical records, and view appointments. Can't create patients, create prescriptions, or access billing.

### Pharmacist
Can view all prescriptions, dispense medications, and view limited patient info (for safety). Can't see full medical records or schedule appointments.

### Lab Technician
Can view lab test orders, upload test results, and view limited patient info. Can't see prescriptions or access billing.

### Billing Officer
Can view all billing records, create and edit billing, view financial reports, and export financial data. Can't see medical records or schedule appointments.

## Responsive Design

Works on desktop, tablet, and mobile. The interface adapts to different screen sizes, and forms are touch-friendly on mobile devices.

## Performance

I tried to keep it fast:
- Optimized database queries with proper indexing
- Data loads on demand (lazy loading)
- Session-based caching for user data
- No heavy JavaScript frameworks
- Efficient CSS and JavaScript

## Customization

The code is organized so you can easily customize things:
- Modular architecture - each feature is separate
- CSS custom properties for easy theming
- Database schema is straightforward to modify
- API-first design makes it easy to integrate with other systems

## Documentation

**For End Users:**
- **USER_MANUAL.md** - Complete user guide for using the system
- **TEST_DOCUMENT.md** - Testing checklist and procedures

**For Developers/Administrators (Optional - Technical Details):**
Located in the `setup/` folder:
- **setup/RBAC_IMPLEMENTATION.md** - Technical RBAC implementation details
- **setup/RBAC_USERS_GUIDE.md** - RBAC user accounts and permissions matrix
- **setup/STAFF_SCHEDULING_GUIDE.md** - Staff scheduling technical setup guide

**Payments & Deployment:**
- **docs/MPESA_INTEGRATION.md** - Full Daraja setup guide (credentials, callbacks, testing matrix)
- **docs/M-PESA_RATE_LIMITING.md** - Handling throttling and retries in production
- **docs/MPESA_PRODUCTION_SETUP.md** - Hardening tips before going live

*Note: The technical documentation files are optional. All essential information for users is covered in USER_MANUAL.md and TEST_DOCUMENT.md.*

## Payment Integration Checklist (M-Pesa)

1. Configure the sandbox credentials first (see `docs/MPESA_INTEGRATION.md`).
2. Whitelist your callback URL with Safaricom and ensure it points to `/backend/api/mpesa_callback.php`.
3. Use the Billing module to trigger STK Push requests—each attempt is logged in `mpesa_transactions` and `mpesa_logs`.
4. Monitor the **Billing → Payment Status** panel or run the `query_status` API action to track pending transactions.
5. When ready for production, switch the `MPESA_PROD_*` environment variables and follow the rate-limiting recommendations.

## Getting Help

If you run into issues:
1. Check the API documentation above
2. Look at the database schema in `backend/init_db.php`
3. Check the JavaScript in `frontend/js/app.js`
4. Read the other documentation files
5. Try the sample data to see how things should work

## License

This is open source under the MIT License. Feel free to use it, modify it, and share it.

---

**Version:** 2.1  
**Last Updated:** December 2025  
**Status:** Production Ready
