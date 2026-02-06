# Migrating from SQLite to PostgreSQL (Supabase)

## Overview
This guide will walk you through migrating your Hospital Management System from SQLite to PostgreSQL using Supabase.

## Prerequisites
- Active Supabase account and project
- Existing SQLite database with data (optional)

---

## Step 1: Get Your Supabase Credentials

1. Go to your [Supabase Dashboard](https://app.supabase.com)
2. Select your project (or create a new one)
3. Navigate to **Settings** → **Database**
4. Scroll to **Connection Info** or **Connection String**
5. Note down these values:
   - **Host**: `db.xxxxxxxxxxxxx.supabase.co`
   - **Port**: `5432` (default)
   - **Database**: `postgres` (default)
   - **User**: `postgres` (default)
   - **Password**: Your project password

### Finding Connection String
You can also use the connection string format:
```
postgresql://postgres:[YOUR-PASSWORD]@db.xxxxxxxxxxxxx.supabase.co:5432/postgres
```

---

## Step 2: Update Your .env File

Update your `.env` file with your Supabase credentials:

```env
DB_CONNECTION=pgsql
DB_HOST=db.xxxxxxxxxxxxx.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=your-actual-supabase-password
```

**Important Notes:**
- Replace `xxxxxxxxxxxxx` with your actual Supabase project reference
- Replace `your-actual-supabase-password` with your project's database password
- Keep the database name as `postgres` (Supabase default)

---

## Step 3: Test the Connection

Test your PostgreSQL connection:

```powershell
php backend/test_pdo.php
```

You should see a success message confirming the connection to PostgreSQL.

---

## Step 4: Initialize Database Schema

This will create all necessary tables in your Supabase database:

```powershell
php backend/init_db.php
```

This creates:
- users
- patients
- doctors
- rooms
- appointments
- medical_records
- billing
- audit_trail

**Default Admin User Created:**
- Email: `admin@hospital.com`
- Password: `admin123`

---

## Step 5: Migrate Existing Data (Optional)

If you have existing data in SQLite that you want to migrate:

```powershell
php backend/migrate_to_postgres.php
```

This script will:
- ✓ Export all data from SQLite
- ✓ Import data into PostgreSQL (Supabase)
- ✓ Verify row counts match
- ✓ Handle data type conversions
- ✓ Show a detailed migration report

---

## Step 6: Verify in Supabase Dashboard

1. Go to your Supabase Dashboard
2. Click on **Table Editor** (left sidebar)
3. You should see all your tables listed
4. Click on each table to verify your data was migrated correctly

---

## Step 7: Update Additional Scripts (if needed)

If you have additional migration scripts, run them:

```powershell
# RBAC users
php backend/add_rbac_users.php

# Sample data
php backend/add_sample_kenyan_data.php

# Scheduling tables
php backend/setup_scheduling_tables.php

# M-Pesa integration tables
php backend/migrations/add_mpesa_tables.php
```

---

## Step 8: Test Your Application

1. Start your development server:
   ```powershell
   php -S localhost:8000 router.php
   ```

2. Open your browser to `http://localhost:8000`

3. Test key functionality:
   - Login with admin credentials
   - Create/view patients
   - Create/view appointments
   - Check medical records
   - Test billing

---

## Troubleshooting

### Connection Refused Error
- Check your Supabase credentials are correct
- Ensure your IP is allowed (Supabase → Settings → Database → Connection pooling)
- Verify the host URL is correct

### SSL Connection Error
If you get SSL errors, you may need to add SSL mode to your DSN in `backend/config.php`:
```php
'dsn' => sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=require', $host, $port, $database),
```

### Data Type Mismatches
The migration script handles most conversions, but if you encounter issues:
- Boolean values: SQLite uses 0/1, PostgreSQL uses true/false
- Dates: SQLite uses TEXT, PostgreSQL uses proper DATE types
- Timestamps: PostgreSQL uses TIMESTAMPTZ for timezone-aware timestamps

---

## Benefits of Using Supabase

✅ **Automatic Backups**: Daily backups of your database  
✅ **Scalability**: Easily scale as your data grows  
✅ **Security**: Built-in Row Level Security (RLS)  
✅ **Real-time**: Optional real-time subscriptions  
✅ **Dashboard**: Easy-to-use administration interface  
✅ **Free Tier**: Generous free tier for development  

---

## Keeping SQLite as Backup

You can keep your SQLite database as a backup. The system will use PostgreSQL when `DB_CONNECTION=pgsql` is set in `.env`.

To switch back to SQLite (for testing):
```env
DB_CONNECTION=sqlite
DB_PATH=backend/data/database.sqlite
```

---

## Performance Tips

1. **Add Indexes**: Consider adding indexes for frequently queried columns
2. **Connection Pooling**: Use Supabase's connection pooler for better performance
3. **Query Optimization**: Use EXPLAIN ANALYZE to optimize slow queries
4. **Monitoring**: Use Supabase Dashboard → Reports to monitor performance

---

## Next Steps

- [ ] Update `.env` with Supabase credentials
- [ ] Test connection with `test_pdo.php`
- [ ] Initialize schema with `init_db.php`
- [ ] Migrate data with `migrate_to_postgres.php` (if needed)
- [ ] Verify in Supabase Dashboard
- [ ] Test application thoroughly
- [ ] Update production environment variables
- [ ] Set up automatic backups strategy

---

## Support

If you encounter issues:
1. Check Supabase status: https://status.supabase.com
2. Review Supabase docs: https://supabase.com/docs/guides/database
3. Check application logs in `backend/logs/`
4. Verify .env configuration

---

**Migration Complete!** 🎉

Your Hospital Management System is now running on PostgreSQL via Supabase!
