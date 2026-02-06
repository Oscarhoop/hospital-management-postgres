# 🚀 Quick Start: Migrating to Supabase PostgreSQL

## What You Need

1. **Supabase Account** - Sign up at [supabase.com](https://supabase.com)
2. **Database Credentials** from Supabase Dashboard
3. **5 minutes** of your time

---

## 🎯 Fastest Path (Interactive Script)

```powershell
.\setup-supabase.ps1
```

This interactive script will:
- ✓ Guide you through the entire process
- ✓ Test your connection
- ✓ Initialize database schema
- ✓ Migrate existing data (if you have any)
- ✓ Verify everything works

---

## 📝 Manual Setup (Step by Step)

### 1️⃣ Get Supabase Credentials

1. Log into [Supabase Dashboard](https://app.supabase.com)
2. Go to **Settings** → **Database** → **Connection Info**
3. Copy these values:

```
Host: db.xxxxxxxxxxxxx.supabase.co
Port: 5432
Database: postgres
User: postgres
Password: [your-password]
```

### 2️⃣ Update .env File

Open `.env` and update with your credentials:

```env
DB_CONNECTION=pgsql
DB_HOST=db.xxxxxxxxxxxxx.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=your-actual-password
```

### 3️⃣ Run Migration

```powershell
# Test connection
php backend/test_pdo.php

# Initialize database
php backend/init_db.php

# Migrate existing data (optional)
php backend/migrate_to_postgres.php
```

### 4️⃣ Start Your App

```powershell
php -S localhost:8000 router.php
```

Login: `admin@hospital.com` / `admin123`

---

## 📁 Files Created

| File | Purpose |
|------|---------|
| `MIGRATION_GUIDE.md` | Comprehensive migration documentation |
| `setup-supabase.ps1` | Interactive setup script |
| `backend/migrate_to_postgres.php` | Data migration tool |
| `.env.supabase.template` | Configuration template |

---

## ❓ Common Issues

### Can't Connect?
- ✓ Check credentials in `.env`
- ✓ Verify Supabase project is active
- ✓ Check firewall/network settings

### Migration Fails?
- ✓ Run `init_db.php` first
- ✓ Check SQLite database path
- ✓ Review error messages carefully

---

## 🎓 Why Supabase?

- **Free Tier**: Perfect for development
- **Auto-backups**: Daily automatic backups
- **Dashboard**: Easy data management
- **Scalable**: Grows with your app
- **Secure**: Built-in security features

---

## 📚 Need Help?

- 📖 Read: `MIGRATION_GUIDE.md` for detailed instructions
- 🐛 Check: `backend/logs/` for error logs
- 🔧 Test: `php backend/test_pdo.php` to verify connection

---

**Ready to migrate?** Run `.\setup-supabase.ps1` and follow the prompts! 🚀
