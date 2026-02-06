# 🏥 Hospital Management System - Supabase PostgreSQL Setup

## 📋 Overview

Your Hospital Management System now supports **PostgreSQL via Supabase**! This gives you:
- ☁️ Cloud-hosted database
- 📊 Real-time dashboard
- 🔒 Enhanced security
- 📈 Better scalability
- 💾 Automatic backups

---

## 🚀 Quick Start

### Option 1: Automated Setup (Recommended)

```powershell
.\setup-supabase.ps1
```

Follow the interactive prompts to complete the migration!

### Option 2: Manual Setup

See `MIGRATION_GUIDE.md` for detailed step-by-step instructions.

---

## 📦 What's Included

### New Files

1. **`MIGRATION_GUIDE.md`** - Complete migration documentation
2. **`QUICKSTART_SUPABASE.md`** - Quick reference guide
3. **`setup-supabase.ps1`** - Interactive setup script
4. **`backend/migrate_to_postgres.php`** - Data migration tool
5. **`.env.supabase.template`** - Configuration template

### Enhanced Files

- **`backend/test_pdo.php`** - Now shows detailed connection info
- **`backend/config.php`** - Already supports both SQLite and PostgreSQL

---

## 🎯 Migration Process

```
Step 1: Get Supabase Credentials
   ↓
Step 2: Update .env File
   ↓
Step 3: Test Connection (test_pdo.php)
   ↓
Step 4: Initialize Database (init_db.php)
   ↓
Step 5: Migrate Data (migrate_to_postgres.php) [Optional]
   ↓
Step 6: Verify in Supabase Dashboard
   ↓
✓ Done! Your app is now using PostgreSQL
```

---

## 🔑 Required Supabase Credentials

You'll need these from your **Supabase Dashboard → Settings → Database**:

- `DB_HOST` - Your Supabase database host (e.g., `db.xxxxx.supabase.co`)
- `DB_PORT` - Default: `5432`
- `DB_DATABASE` - Default: `postgres`
- `DB_USERNAME` - Default: `postgres`
- `DB_PASSWORD` - Your project password

---

## 📝 Example .env Configuration

```env
# PostgreSQL via Supabase
DB_CONNECTION=pgsql
DB_HOST=db.abcdefghijklmno.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=your-super-secret-password
```

---

## ✅ Testing Your Setup

After configuration, test each step:

```powershell
# 1. Test database connection
php backend/test_pdo.php

# 2. Initialize database schema
php backend/init_db.php

# 3. (Optional) Migrate existing data
php backend/migrate_to_postgres.php

# 4. Start development server
php -S localhost:8000 router.php
```

Login with:
- **Email:** `admin@hospital.com`
- **Password:** `admin123`

---

## 🔄 Switching Between SQLite and PostgreSQL

Your system supports both! Just change `DB_CONNECTION` in `.env`:

### Use PostgreSQL (Supabase)
```env
DB_CONNECTION=pgsql
```

### Use SQLite (Local)
```env
DB_CONNECTION=sqlite
DB_PATH=backend/data/database.sqlite
```

---

## 📊 Database Tables

Your PostgreSQL database will include:

- **users** - System users and authentication
- **patients** - Patient records
- **doctors** - Doctor information
- **rooms** - Hospital rooms/facilities
- **appointments** - Appointment scheduling
- **medical_records** - Medical history and records
- **billing** - Payment and billing information
- **audit_trail** - System activity logs

*(Plus additional tables from migrations like M-Pesa integration, scheduling, etc.)*

---

## 🛠️ Troubleshooting

### "Connection Refused"
- ✓ Check credentials in `.env`
- ✓ Verify Supabase project is active
- ✓ Ensure internet connection is stable

### "Authentication Failed"
- ✓ Double-check `DB_PASSWORD` in `.env`
- ✓ Reset password in Supabase Dashboard if needed

### "Table Already Exists"
- This is normal if you ran `init_db.php` multiple times
- The script drops and recreates tables each time

### "Could Not Find Driver"
- ✓ Ensure PHP PDO PostgreSQL extension is installed
- ✓ Run: `php -m | findstr pgsql` to verify

---

## 📚 Documentation

- **`MIGRATION_GUIDE.md`** - Comprehensive migration guide
- **`QUICKSTART_SUPABASE.md`** - Quick reference
- **Supabase Docs** - https://supabase.com/docs
- **PostgreSQL Docs** - https://www.postgresql.org/docs/

---

## 🔐 Security Best Practices

1. **Never commit `.env` to version control**
   - Already in `.gitignore`
   - Use `.env.example` for templates

2. **Use strong passwords**
   - Generate strong password for `DB_PASSWORD`
   - Change default admin password after first login

3. **Enable RLS in Supabase** (Optional)
   - Row Level Security for additional protection
   - Configure in Supabase Dashboard → Authentication

4. **Regular backups**
   - Supabase provides automatic daily backups
   - Consider exporting important data regularly

---

## 🎓 Next Steps After Migration

1. **Verify Data**
   - Check all tables in Supabase Dashboard
   - Test key application features

2. **Set Up Additional Features**
   - Run migrations for RBAC, scheduling, M-Pesa
   - Add sample data for testing

3. **Configure Production**
   - Update production `.env` with Supabase credentials
   - Test in production environment

4. **Monitor Performance**
   - Use Supabase Dashboard → Reports
   - Monitor query performance and usage

---

## 💡 Benefits You'll Notice

- **Faster Queries** - PostgreSQL is optimized for complex queries
- **Better Concurrency** - Multiple users can access simultaneously
- **Advanced Features** - JSON support, full-text search, etc.
- **Scalability** - Easily handle growth in data and users
- **Reliability** - Built-in high availability and backups

---

## 📞 Support Resources

- **Supabase Status**: https://status.supabase.com
- **Supabase Discord**: https://discord.supabase.com
- **Check Logs**: `backend/logs/` for application errors
- **Test Connection**: `php backend/test_pdo.php`

---

## ✨ You're All Set!

Your Hospital Management System is ready to use PostgreSQL via Supabase!

**Quick Commands:**
```powershell
# Test connection
php backend/test_pdo.php

# Start server
php -S localhost:8000 router.php

# View in browser
http://localhost:8000
```

**Happy coding!** 🚀
