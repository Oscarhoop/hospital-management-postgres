# M-Pesa Credentials - Troubleshooting Guide

## ❌ **Current Issue:**

Your M-Pesa credentials are **not working**. Getting **400 Bad Request** from Safaricom API.

**Error Log:**
- Status Code: 400
- Environment: Sandbox
- Issue: "Failed to generate access token"

---

## 🔧 **How to Fix:**

### **Option 1: Get New Sandbox Credentials** (Recommended for Testing)

1. **Go to Daraja Portal**: https://developer.safaricom.co.ke/
2. **Login** to your account
3. Click **"My Apps"** in the top menu
4. Find your existing app OR click **"Add a New App"**
5. Select **"Lipa Na M-Pesa Sandbox"**
6. Click on your app to view details
7. You'll see:
   - **Consumer Key**
   - **Consumer Secret** (might need to click "Show")
8. **Copy both** and replace in your `.env` file

---

### **Option 2: Use Safaricom Test Credentials** (Quick Test)

Safaricom provides public test credentials for learning. Update your `.env`:

```env
# Temporary test credentials (publicly available)
MPESA_SANDBOX_CONSUMER_KEY=9v38Dtu5u2BpsITPmLcXNWGMsjZRWSTG
MPESA_SANDBOX_CONSUMER_SECRET=bclwIPTv67GtoXA7
MPESA_SANDBOX_PASSKEY=bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919
MPESA_SANDBOX_SHORTCODE=174379
```

**Note:** These are demo keys - they work but are shared publicly. Get your own for production!

---

### **Option 3: Check if Daraja is Down**

Sometimes Safaricom's Daraja API has issues. Check:
- https://developer.safaricom.co.ke/support
- Try again in 30 minutes

---

## 🧪 **Test Your New Credentials:**

After updating `.env`, run:

```powershell
php backend/test_mpesa_credentials.php
```

You should see:
```
✓ SUCCESS! Access token generated.
```

---

## 📝 **Common Issues:**

### **400 Bad Request**
- ✗ Invalid Consumer Key or Secret
- ✗ Extra spaces in credentials
- ✗ Wrong environment (sandbox vs production)
- ✗ Credentials expired

### **401 Unauthorized**
- ✗ Wrong Consumer Key/Secret combination
- ✗ Credentials not active

### **500 Server Error**
- ✗ Daraja API might be down
- ✗ Try again later

---

## 🎯 **Quick Fix - Use Public Test Keys:**

**Replace lines in your `.env` with these PUBLIC test credentials:**

```env
MPESA_SANDBOX_CONSUMER_KEY=9v38Dtu5u2BpsITPmLcXNWGMsjZRWSTG
MPESA_SANDBOX_CONSUMER_SECRET=bclwIPTv67GtoXA7
```

These are from Safaricom's own documentation and should work immediately!

---

## ✅ **After Getting Working Credentials:**

1. Update `.env` file
2. Restart your server
3. Test payment again
4. Check success with: `php backend/check_mpesa_logs.php`

---

**Try the public test keys first to verify everything else is working!** 🚀
