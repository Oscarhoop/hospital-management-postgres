# Render Deployment Guide

## Overview
This guide covers deploying the Hospital Management System to Render.com using PostgreSQL database.

## Prerequisites
- Render.com account
- GitHub repository with the project code
- PostgreSQL database (handled automatically by render.yaml)

## Quick Setup

### 1. Push to GitHub
```bash
git add .
git commit -m "Ready for Render deployment with PostgreSQL"
git push origin main
```

### 2. Create Render Account
1. Go to [render.com](https://render.com)
2. Sign up with GitHub
3. Authorize Render to access your repository

### 3. Deploy to Render
1. Click "New +" → "Web Service"
2. Connect your GitHub repository
3. Select the repository
4. Render will automatically detect the `render.yaml` file
5. Click "Deploy Web Service"

### 4. Database Setup
The `render.yaml` file will automatically create:
- **Web Service**: Hospital Management System
- **PostgreSQL Database**: `hospital_mgmt` database

### 5. Environment Variables
Render will automatically set these from render.yaml:
- `DB_CONNECTION=pgsql`
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

### 6. Configure M-Pesa (Optional)
In Render dashboard → Web Service → Environment:
1. Add M-Pesa credentials:
   - `MPESA_CONSUMER_KEY`
   - `MPESA_CONSUMER_SECRET`
   - `MPESA_PASSKEY`
   - `MPESA_SHORTCODE`
   - `MPESA_CALLBACK_URL`

## Manual Deployment (Alternative)

If not using render.yaml:

### 1. Create PostgreSQL Database
1. In Render: "New +" → "PostgreSQL"
2. Name: `postgres`
3. Database: `hospital_mgmt`
4. User: `hospital_app`
5. Save connection details

### 2. Create Web Service
1. In Render: "New +" → "Web Service"
2. Connect repository
3. Runtime: Docker
4. Environment Variables:
   ```
   DB_CONNECTION=pgsql
   DB_HOST=<postgres-hostname>
   DB_PORT=5432
   DB_DATABASE=hospital_mgmt
   DB_USERNAME=hospital_app
   DB_PASSWORD=<postgres-password>
   ```

## First Time Setup

### 1. Access Your Application
Once deployed, your app will be available at:
`https://your-app-name.onrender.com`

### 2. Create Admin User
The first user needs to be created manually:
1. Access the database via Render's pgAdmin
2. Run:
```sql
INSERT INTO users (name, email, password, role, created_at)
VALUES ('Admin', 'admin@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', CURRENT_TIMESTAMP);
```

### 3. Add Sample Data (Optional)
To populate with sample data:
1. Go to: `https://your-app-name.onrender.com/backend/add_sample_kenyan_data.php`
2. This will create sample users, patients, doctors, etc.

## Important Notes

### Database Persistence
- Render's PostgreSQL automatically persists data
- Backups are handled by Render
- No additional configuration needed

### Performance
- Free tier has limited resources
- Consider upgrading for production use
- Monitor performance in Render dashboard

### SSL/HTTPS
- Render automatically provides SSL certificates
- All traffic is encrypted by default

### Custom Domain (Optional)
1. In Render dashboard → Web Service → Custom Domains
2. Add your custom domain
3. Update DNS records as instructed

## Troubleshooting

### Database Connection Issues
1. Check environment variables match database details
2. Verify database is running
3. Check Render logs for connection errors

### Build Failures
1. Check Dockerfile syntax
2. Verify all files are committed to Git
3. Review build logs in Render dashboard

### Runtime Errors
1. Check application logs in Render dashboard
2. Verify database schema is created
3. Ensure all PHP extensions are installed

## Production Considerations

### Security
- Change default admin password immediately
- Use strong database passwords
- Enable Render's security features
- Configure M-Pesa production credentials

### Scaling
- Monitor resource usage
- Consider upgrading to paid plans
- Set up monitoring and alerts

### Backups
- Render handles database backups automatically
- Consider additional backup strategies for important data

## Support

For issues:
1. Check Render documentation
2. Review application logs
3. Test locally with same configuration
4. Contact Render support for platform issues
