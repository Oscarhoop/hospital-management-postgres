# M-Pesa Integration Setup Guide

## ✅ Database Setup Complete

Your M-Pesa tables have been successfully created in PostgreSQL:
- ✓ `mpesa_transactions` - Transaction records
- ✓ `mpesa_logs` - API request/response logs
- ✓ `billing` table - Updated with M-Pesa columns

---

## 🔑 Get Your M-Pesa Credentials

To enable M-Pesa payments, you need to get credentials from the Safaricom Daraja Portal:

### **Step 1: Register on Daraja Portal**

1. Go to: https://developer.safaricom.co.ke/
2. Click **"Sign up"**
3. Fill in your details and verify your email
4. Log in to the portal

### **Step 2: Create a Sandbox App (for Testing)**

1. Once logged in, go to **"My Apps"**
2. Click **"Create New App"**
3. Select **"Lipa Na M-Pesa Sandbox"**
4. Fill in the app details:
   - App Name: "Hospital Management System"
   - Description: "Payment integration for hospital services"
5. Click **"Create App"**

### **Step 3: Get Your Credentials**

After creating the app, you'll see:

```
Consumer Key: AbCdEf123456...
Consumer Secret: XyZ789...
Passkey: bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919
```

---

## 📝 Update Your .env File

Open your `.env` file and replace these values:

```env
# Replace these with your actual credentials from Daraja
MPESA_SANDBOX_CONSUMER_KEY=your_actual_consumer_key_here
MPESA_SANDBOX_CONSUMER_SECRET=your_actual_consumer_secret_here

# These are pre-filled with Safaricom sandbox defaults:
MPESA_SANDBOX_PASSKEY=bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919
MPESA_SANDBOX_SHORTCODE=174379
MPESA_SANDBOX_INITIATOR_NAME=testapi
MPESA_SANDBOX_INITIATOR_PASSWORD=Safaricom999!*!
```

---

## 🌐 Set Up Callback URLs (for Production)

For production, you'll need public URLs for callbacks. You have two options:

### **Option A: Deploy to a Server**
Deploy your app to a server with a public domain, then update:
```env
MPESA_SANDBOX_CALLBACK_URL=https://yourdomain.com/backend/api/mpesa_callback.php
MPESA_SANDBOX_TIMEOUT_URL=https://yourdomain.com/backend/api/mpesa_timeout.php
MPESA_SANDBOX_RESULT_URL=https://yourdomain.com/backend/api/mpesa_result.php
```

### **Option B: Use ngrok for Local Testing**

1. Download ngrok: https://ngrok.com/download
2. Run: `ngrok http 8000`
3. Copy the HTTPS URL (e.g., `https://abc123.ngrok.io`)
4. Update callbacks in `.env`:
```env
MPESA_SANDBOX_CALLBACK_URL=https://abc123.ngrok.io/backend/api/mpesa_callback.php
```

---

## 🧪 Testing M-Pesa Sandbox

### **Test Phone Numbers:**
Safaricom provides test phone numbers for sandbox:
- **254708374149** - Always succeeds
- **254711111111** - Simulates timeout
- **254722222222** - Simulates insufficient funds

### **Test Amount:**
- Any amount from **1 to 70,000 KES**

### **Testing Steps:**
1. Log in to your hospital app
2. Go to **Billing** section
3. Create a bill or select existing bill
4. Click **"Pay with M-Pesa"**
5. Enter test phone number: `254708374149`
6. Enter amount
7. You'll receive a simulated STK Push
8. Enter PIN: `12345` (sandbox PIN)

---

## 📋 M-Pesa API Files

Your app already has these M-Pesa API files:
- `backend/api/mpesa_callback.php` - Handles payment confirmations
- `backend/api/mpesa_timeout.php` - Handles timeouts
- `backend/api/mpesa_result.php` - Handles transaction results
- `backend/config/mpesa_config.php` - Configuration

---

## 🔄 Switch to Production

When ready for live transactions:

1. Create a **Production App** on Daraja Portal
2. Get production credentials
3. Update `.env`:
```env
MPESA_ENVIRONMENT=production

MPESA_PROD_CONSUMER_KEY=your_prod_key
MPESA_PROD_CONSUMER_SECRET=your_prod_secret
MPESA_PROD_PASSKEY=your_prod_passkey
MPESA_PROD_SHORTCODE=your_till_or_paybill
```

---

## 🐛 Troubleshooting

### **"Invalid Access Token"**
- Check your Consumer Key and Secret are correct
- Ensure they're for the right environment (sandbox/production)

### **"Invalid Shortcode"**
- Sandbox shortcode should be: `174379`
- Production: Use your actual Till Number or PayBill

### **"Callback URL not reachable"**
- Must be a public HTTPS URL
- Use ngrok for local testing
- Check firewall settings

### **No STK Push Received**
- Phone number must be in format: `254XXXXXXXXX`
- Number must be Safaricom (starts with 254-7XX or 254-1XX)
- Check M-Pesa logs table for errors

---

## 📊 View Transaction Logs

Check M-Pesa transactions in:
1. **Supabase Dashboard** → Table Editor → `mpesa_transactions`
2. **Or in your app** → Billing section → View transaction history

---

## 🔗 Useful Links

- **Daraja Portal**: https://developer.safaricom.co.ke/
- **API Documentation**: https://developer.safaricom.co.ke/APIs
- **Test Credentials**: https://developer.safaricom.co.ke/test_credentials
- **Support**: https://developer.safaricom.co.ke/support

---

## ✅ Quick Checklist

- [ ] Register on Daraja Portal
- [ ] Create Sandbox App
- [ ] Get Consumer Key & Secret
- [ ] Update `.env` with credentials
- [ ] Test with sandbox phone number (254708374149)
- [ ] Verify transactions in database
- [ ] Set up public callback URLs (for production)
- [ ] Apply for Production App (when ready)

---

**Your M-Pesa integration is ready!** Just add your Daraja credentials to get started! 🚀💳
