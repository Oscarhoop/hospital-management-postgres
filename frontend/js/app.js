let currentUser = null;
let currentSection = 'dashboard';
let currentModal = null;
let currentEditId = null;

const API_BASE = '../backend/api/';
function showAlert(message, type = 'success') {
    const alert = document.getElementById('alert');
    alert.textContent = message;
    alert.className = `alert alert-${type} show`;
    setTimeout(() => {
        alert.classList.remove('show');
    }, 4000);
}

function showToast(title, message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    const icon = type === 'success' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-times-circle"></i>';
    
    toast.innerHTML = `
        <div class="toast-icon">${icon}</div>
        <div class="toast-content">
            <div class="toast-title">${escapeHtml(title)}</div>
            ${message ? `<div class="toast-message">${escapeHtml(message)}</div>` : ''}
        </div>
    `;
    
    container.appendChild(toast);
    
    // Remove toast after animation completes
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function parseDateValue(value) {
    if (!value) return null;
    const hasTimezone = /([zZ]|[+-]\d{2}:?\d{2})$/.test(value);
    const normalized = hasTimezone ? value : `${value}Z`;
    const date = new Date(normalized);
    return isNaN(date.getTime()) ? null : date;
}

function formatDate(dateString) {
    const date = parseDateValue(dateString);
    if (!date) return 'Not specified';
    return date.toLocaleDateString(undefined, { timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone });
}

// Global fetch wrapper with credentials
async function apiFetch(url, options = {}) {
    const defaults = {
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json'
        }
    };
    
    const mergedOptions = {
        ...defaults,
        ...options
    };
    
    const response = await fetch(url, mergedOptions);
    
    if (response.status === 401) {
        window.location.href = '/frontend/index.html?session=expired';
        return;
    }
    
    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    return response.json();
}

function formatDateTime(dateString) {
    const date = parseDateValue(dateString);
    if (!date) return 'Not specified';
    return date.toLocaleString(undefined, { timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone });
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: 'KES'
    }).format(amount);
}

async function handleLogin(e) {
    e.preventDefault();
    const email = document.getElementById('loginEmailPage').value;
    const password = document.getElementById('loginPasswordPage').value;
    
    if (!email || !password) {
        showAlert('Please enter email and password', 'error');
        return;
    }
    
    try {
        const response = await fetch(API_BASE + 'auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'login', email, password })
        });
        
        let data;
        const text = await response.text();
        try { data = JSON.parse(text); } catch (e) {
            console.error('Login response was not valid JSON:', text);
            throw new Error('Invalid server response');
        }
        
        console.log('[LOGIN] Response data:', data);
        if (data.success && data.user) {
            console.log('[LOGIN] Login successful, user:', data.user);
            currentUser = data.user;
            console.log('[LOGIN] currentUser set to:', currentUser);
            showMainApp();
            showToast('Welcome!', `Logged in as ${currentUser.name}`, 'success');
            
            if (typeof RBAC === 'undefined' || typeof hasPermission === 'undefined') {
                console.error('[LOGIN] RBAC or hasPermission not defined!');
                loadDashboard();
                showSection('dashboard');
                return;
            }
            
            updateNavigationVisibility();
            
            if (hasPermission('dashboard', 'view')) {
                console.log('[LOGIN] User has dashboard permission, loading...');
                loadDashboard();
                showSection('dashboard');
            } else {
                console.log('[LOGIN] User does not have dashboard permission');
                const allowedNav = RBAC.getNavigation(currentUser.role);
                console.log('[LOGIN] Allowed navigation:', allowedNav);
                if (allowedNav.length > 0) {
                    showSection(allowedNav[0]);
                } else {
                    showSection('patients'); // fallback
                }
            }
        } else {
            console.error('[LOGIN] Login failed:', data);
            showToast('Login Failed', data.error === 'Invalid credentials' ? 'Wrong email or password.' : data.error || 'Login failed', 'error');
        }
    } catch (err) {
        showToast('Login Error', 'An unexpected error occurred during login.', 'error');
    }
}

async function logout() {
    try {
        await fetch(API_BASE + 'auth.php?action=logout', { credentials: 'same-origin' });
        currentUser = null;
        showLoginPage();
        showToast('Logged Out', 'You have been logged out successfully', 'success');
    } catch (err) {
        showAlert('Logout error: ' + err.message, 'error');
    }
}

function showLoginPage() {
    document.getElementById('loginPage').classList.remove('hidden');
    document.getElementById('mainApp').classList.remove('active');
    document.getElementById('loginEmailPage').value = '';
    document.getElementById('loginPasswordPage').value = '';
}

function showMainApp() {
    document.getElementById('loginPage').classList.add('hidden');
    document.getElementById('mainApp').classList.add('active');
    updateUI();
    updateNavigationVisibility(); // Apply RBAC visibility
}

function updateUI() {
    if (!currentUser) {
        // Ensure all sidebar elements are hidden if not logged in
        document.getElementById('sidebarUserInfo').style.display = 'none';
        document.getElementById('sidebarLogoutBtn').style.display = 'none';
        return;
    }
    
    // Get user elements
    const sidebarUserInfo = document.getElementById('sidebarUserInfo');
    const sidebarUserName = document.getElementById('sidebarUserName');
    const sidebarUserRole = document.getElementById('sidebarUserRole');
    const sidebarLogoutBtn = document.getElementById('sidebarLogoutBtn');
    
    // Show sidebar elements
    if (sidebarUserInfo) sidebarUserInfo.style.display = 'flex';
    if (sidebarLogoutBtn) sidebarLogoutBtn.style.display = 'flex';

    // Set Name and Role
    const roleInfo = RBAC.getRoleInfo(currentUser.role);
    if (sidebarUserName) {
        // Display name without role badge here (Role badge is in sidebarUserRole)
        sidebarUserName.textContent = escapeHtml(currentUser.name);
    }
    if (sidebarUserRole) {
        // Display role label
        sidebarUserRole.textContent = roleInfo.label;
    }

    updateNavigationVisibility();
}

function updateNavigationVisibility() {
    if (!currentUser || !currentUser.role || !window.RBAC) return;
    
    const role = currentUser.role;
    const allowedNav = RBAC.getNavigation(role);
    
    const navSections = {
        'dashboard': 'dashboard',
        'patients': 'patients',
        'appointments': 'appointments',
        'doctors': 'doctors',
        'rooms': 'rooms',
        'medical-records': 'medical-records',
        'billing': 'billing',
        'reports': 'reports',
        'users': 'users',
        'schedules': 'schedules'
    };
    
    // Map RBAC navigation keys to actual section names
    const rbacToSectionMap = {
        'dashboard': 'dashboard',
        'patients': 'patients',
        'appointments': 'appointments',
        'doctors': 'doctors',
        'rooms': 'rooms',
        'medical_records': 'medical-records',
        'billing': 'billing',
        'reports': 'reports',
        'users': 'users',
        'schedules': 'schedules'
    };
    
    // Hide/show navigation items based on RBAC
    Object.keys(navSections).forEach(section => {
        const navBtn = document.querySelector(`[onclick=\"showSection('${section}')\"]`);
        if (navBtn) {
            // Check if this section is allowed for the user's role
            const rbacKey = Object.keys(rbacToSectionMap).find(key => rbacToSectionMap[key] === section);
            const isAllowed = allowedNav.includes(rbacKey || section);
            navBtn.style.display = isAllowed ? 'block' : 'none';
        }
    });

    // RBAC for utility buttons
    const exportBtn = document.getElementById('sidebarExportBtn');
    if (exportBtn) {
        exportBtn.style.display = role === 'admin' ? 'block' : 'none';
    }
    const searchBtn = document.getElementById('sidebarSearchBtn');
    if (searchBtn) {
        searchBtn.style.display = 'block'; // available to all roles
    }

    // Show audit trail for admins - only in submenu
    const auditTrailMenuBtn = document.getElementById('auditTrailMenuBtn');
    if (auditTrailMenuBtn) {
        auditTrailMenuBtn.style.display = role === 'admin' ? 'flex' : 'none';
    }

    // RBAC for "Add" buttons in sections
    const setDisplay = (id, allowed) => {
        const el = document.getElementById(id);
        if (el) el.style.display = allowed ? 'inline-flex' : 'none';
    };
    setDisplay('patientsAddBtn', RBAC.hasPermission(role, 'patients', 'add'));
    setDisplay('appointmentsAddBtn', RBAC.hasPermission(role, 'appointments', 'add'));
    setDisplay('doctorsAddBtn', RBAC.hasPermission(role, 'doctors', 'add'));
    setDisplay('roomsAddBtn', RBAC.hasPermission(role, 'rooms', 'add'));
    setDisplay('recordsAddBtn', RBAC.hasPermission(role, 'medical_records', 'add'));
    setDisplay('billingAddBtn', RBAC.hasPermission(role, 'billing', 'create'));
    setDisplay('usersAddBtn', RBAC.hasPermission(role, 'users', 'add'));
    setDisplay('schedulesAddBtn', RBAC.hasPermission(role, 'schedules', 'add'));
    
    // Update user name with role badge
    const roleInfo = RBAC.getRoleInfo(role);
    const userNameElement = document.getElementById('userName');
    if (userNameElement) {
        userNameElement.innerHTML = `${escapeHtml(currentUser.name)} <span class="role-badge" style="background: ${roleInfo.color}; color: white; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; margin-left: 0.5rem; vertical-align: middle;">${roleInfo.label}</span>`;
    }
}

// Navigation Functions
function showSection(sectionName) {
    console.log('showSection called with:', sectionName);
    // If RBAC is present, prevent navigating to disallowed sections
    if (currentUser && window.RBAC) {
        const allowedNav = RBAC.getNavigation(currentUser.role);
        console.log('Allowed navigation:', allowedNav);
        if (!allowedNav.includes(sectionName)) {
            console.warn(`Section "${sectionName}" not in allowed navigation, redirecting to dashboard`);
            sectionName = allowedNav[0] || 'dashboard';
        }
    }
    // Hide all sections
    document.querySelectorAll('.section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Show selected section
    const sectionElement = document.getElementById(sectionName);
    if (!sectionElement) {
        console.error(`Section with id "${sectionName}" not found`);
        return;
    }
    sectionElement.classList.add('active');
    
    // Remove active from all sidebar nav buttons
    document.querySelectorAll('.sidebar-nav-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Find and activate the correct sidebar nav button
    const navBtn = document.querySelector(`.sidebar-nav-btn[onclick="showSection('${sectionName}')"]`);
    if (navBtn) {
        navBtn.classList.add('active');
    }
    
    currentSection = sectionName;
    
    // Load section data
    switch(sectionName) {
        case 'dashboard':
            loadDashboard();
            break;
        case 'patients':
            loadPatients();
            break;
        case 'appointments':
            loadAppointments();
            // Initialize calendar view if enhancements are loaded
            setTimeout(() => {
                if (typeof initCalendarView === 'function') {
                    initCalendarView();
                }
            }, 300);
            break;
        case 'doctors':
            loadDoctors();
            break;
        case 'rooms':
            loadRooms();
            break;
        case 'medical-records':
            loadMedicalRecords();
            break;
        case 'billing':
            loadBilling();
            break;
        case 'reports':
            // Ensure default report type is selected if not already
            const reportTypeSelect = document.getElementById('reportType');
            if (reportTypeSelect && !reportTypeSelect.value) {
                reportTypeSelect.value = 'revenue';
            }
            loadReport();
            break;
        case 'users':
            loadUsers();
            break;
        case 'audit-trail':
            console.log('Loading audit trail...');
            loadAuditTrail();
            break;
        case 'schedules':
            if (typeof loadSchedules === 'function') {
                loadSchedules();
            }
            break;
    }
}

// User Management Functions
async function loadUsers() {
    try {
        const response = await fetch(API_BASE + 'users.php', { credentials: 'same-origin' });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        const users = Array.isArray(data) ? data : [];
        displayUsers(users);
    } catch (err) {
        console.error('Error loading users:', err);
        showAlert('Error loading users: ' + err.message, 'error');
        displayUsers([]);
    }
}

function displayUsers(users) {
    const tbody = document.querySelector('#usersTable tbody');
    
    if (users.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="empty-state">
                    <div class="empty-state-text">No users found</div>
                    <div class="empty-state-subtext">Add your first user to get started</div>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = users.map(u => `
        <tr>
            <td>
                <div style="font-weight: 500;">${escapeHtml(u.name)}</div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">ID: ${u.id}</div>
            </td>
            <td>${escapeHtml(u.email)}</td>
            <td><span class="status-badge status-${u.role === 'admin' ? 'completed' : 'scheduled'}">${escapeHtml(u.role)}</span></td>
            <td>${escapeHtml(u.phone || 'Not provided')}</td>
            <td>${formatDate(u.created_at)}</td>
            <td class="action-buttons">
                <button class="btn btn-sm btn-secondary" onclick="editUser(${u.id})">
                    Edit
                </button>
                ${u.id != currentUser.id ? `
                <button class="btn btn-sm btn-danger" onclick="deleteUser(${u.id})">
                    Delete
                </button>
                ` : ''}
            </td>
        </tr>
    `).join('');
}

async function loadAuditTrail() {
    console.log('loadAuditTrail function called');
    try {
        const url = API_BASE + 'audit.php?action=get_audit_trail';
        console.log('Fetching audit trail from:', url);
        
        const response = await fetch(url, { credentials: 'same-origin' });
        console.log('Response status:', response.status);
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Response error:', errorText);
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('Audit trail data received:', data);
        const auditTrail = Array.isArray(data) ? data : [];
        console.log('Displaying audit trail with', auditTrail.length, 'entries');
        displayAuditTrail(auditTrail);
    } catch (err) {
        console.error('Error loading audit trail:', err);
        showAlert('Error loading audit trail: ' + err.message, 'error');
        displayAuditTrail([]);
    }
}

// Store original audit trail data for filtering
let allAuditTrailData = [];

function displayAuditTrail(auditTrail) {
    // Store the full dataset for filtering
    allAuditTrailData = auditTrail;
    
    // Update count
    const countElement = document.getElementById('auditTrailCount');
    if (countElement) {
        countElement.textContent = `${auditTrail.length} ${auditTrail.length === 1 ? 'entry' : 'entries'}`;
    }
    
    const tbody = document.querySelector('#auditTrailTable tbody');
    
    if (auditTrail.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="empty-state">
                    <div class="empty-state-text">No audit trail data found</div>
                </td>
            </tr>
        `;
        return;
    }
    
    // Remove existing floating panels before rendering new ones
    document.querySelectorAll('.audit-hover-panel[data-source="audit-trail"]').forEach(panel => panel.remove());
    
    const panelsToInsert = [];
    
    tbody.innerHTML = auditTrail.map((log, index) => {
        // Create unique ID for this row
        const rowId = `audit-row-${index}`;
        
        // Safely parse details JSON
        let detailsHtml = '';
        let hasDetails = false;
        
        if (log.details) {
            try {
                // Try to parse if it's a string, otherwise use as-is
                const parsed = typeof log.details === 'string' ? JSON.parse(log.details) : log.details;
                hasDetails = true;
                const panelId = `auditDetails${rowId}`;
                
                // Check if it's a before/after change log
                if (parsed.before && parsed.after) {
                    // Create human-readable key-value format
                    const changes = getChangedFields(parsed.before, parsed.after);
                    if (changes.length > 0) {
                        const panelContent = `
                            <div class="audit-changes-view">
                                ${changes.map(change => `
                                    <div class="audit-change-row">
                                        <div class="audit-field-label">${formatFieldLabel(change.field)}</div>
                                        <div class="audit-values-comparison">
                                            <div class="audit-value-box audit-value-before-box">
                                                <span class="audit-value-label">Before</span>
                                                <span class="audit-value-text">${formatValueForDisplay(change.before)}</span>
                                            </div>
                                            <i class="fas fa-arrow-right audit-arrow-icon"></i>
                                            <div class="audit-value-box audit-value-after-box">
                                                <span class="audit-value-label">After</span>
                                                <span class="audit-value-text">${formatValueForDisplay(change.after)}</span>
                                            </div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        `;
                        panelsToInsert.push(`
                            <div class="audit-hover-panel" 
                                 id="${panelId}" 
                                 data-pinned="false"
                                 data-row-id="${rowId}"
                                 data-source="audit-trail">
                                <div class="audit-panel-header">
                                    <span>Changes</span>
                                    <button class="audit-pin-btn" data-row-id="${rowId}" title="Pin/Unpin">
                                        <i class="fas fa-thumbtack"></i>
                                    </button>
                                </div>
                                ${panelContent}
                            </div>
                        `);
                        detailsHtml = `
                            <div class="audit-hover-container" data-row-id="${rowId}">
                                <div class="audit-hover-trigger">
                                    <i class="fas fa-info-circle"></i> ${changes.length} change${changes.length !== 1 ? 's' : ''}
                                </div>
                            </div>
                        `;
                    } else {
                        detailsHtml = `<span class="audit-no-changes">No changes detected</span>`;
                    }
                } else if (parsed.filters) {
                    // Handle filter objects - show as simple list
                    const filterKeys = Object.keys(parsed).filter(k => k !== 'filters' && parsed[k]);
                    if (filterKeys.length > 0) {
                        const panelContent = `
                            <div class="audit-simple-list">
                                ${filterKeys.map(key => `
                                    <div class="audit-list-item">
                                        <span class="audit-list-label">${formatFieldLabel(key)}:</span>
                                        <span class="audit-list-value">${formatValueForDisplay(parsed[key])}</span>
                                    </div>
                                `).join('')}
                            </div>
                        `;
                        panelsToInsert.push(`
                            <div class="audit-hover-panel" 
                                 id="${panelId}" 
                                 data-pinned="false"
                                 data-row-id="${rowId}"
                                 data-source="audit-trail">
                                <div class="audit-panel-header">
                                    <span>Filter Details</span>
                                    <button class="audit-pin-btn" data-row-id="${rowId}" title="Pin/Unpin">
                                        <i class="fas fa-thumbtack"></i>
                                    </button>
                                </div>
                                ${panelContent}
                            </div>
                        `);
                        detailsHtml = `
                            <div class="audit-hover-container" data-row-id="${rowId}">
                                <div class="audit-hover-trigger">
                                    <i class="fas fa-filter"></i> Filters
                                </div>
                            </div>
                        `;
                    } else {
                        detailsHtml = `<span class="audit-simple-text">No filters</span>`;
                    }
                } else {
                    // Regular object - show as key-value pairs
                    const keys = Object.keys(parsed);
                    if (keys.length > 0) {
                        const panelContent = `
                            <div class="audit-keyvalue-view">
                                ${keys.map(key => `
                                    <div class="audit-kv-row">
                                        <span class="audit-kv-label">${formatFieldLabel(key)}:</span>
                                        <span class="audit-kv-value">${formatValueForDisplay(parsed[key])}</span>
                                    </div>
                                `).join('')}
                            </div>
                        `;
                        panelsToInsert.push(`
                            <div class="audit-hover-panel" 
                                 id="${panelId}" 
                                 data-pinned="false"
                                 data-row-id="${rowId}"
                                 data-source="audit-trail">
                                <div class="audit-panel-header">
                                    <span>Details</span>
                                    <button class="audit-pin-btn" data-row-id="${rowId}" title="Pin/Unpin">
                                        <i class="fas fa-thumbtack"></i>
                                    </button>
                                </div>
                                ${panelContent}
                            </div>
                        `);
                        detailsHtml = `
                            <div class="audit-hover-container" data-row-id="${rowId}">
                                <div class="audit-hover-trigger" title="Click to view details">
                                    <i class="fas fa-info-circle"></i> ${keys.length} field${keys.length !== 1 ? 's' : ''}
                                </div>
                            </div>
                        `;
                    } else {
                        detailsHtml = `<span class="audit-simple-text">Empty</span>`;
                    }
                }
            } catch (e) {
                // If parsing fails, just display the raw string
                detailsHtml = `<div class="audit-details-simple"><code>${escapeHtml(log.details)}</code></div>`;
                hasDetails = true;
            }
        }
        
        if (!hasDetails) {
            detailsHtml = '<span class="audit-no-details">—</span>';
        }
        
        // Get action icon
        let actionIcon = 'fa-circle';
        let actionClass = '';
        if (log.action.includes('create') || log.action.includes('add')) {
            actionIcon = 'fa-plus-circle';
            actionClass = 'text-success';
        } else if (log.action.includes('update') || log.action.includes('edit')) {
            actionIcon = 'fa-edit';
            actionClass = 'text-warning';
        } else if (log.action.includes('delete') || log.action.includes('remove') || log.action.includes('cancel')) {
            actionIcon = 'fa-trash';
            actionClass = 'text-danger';
        } else if (log.action.includes('login')) {
            actionIcon = 'fa-sign-in-alt';
            actionClass = 'text-info';
        } else if (log.action.includes('logout')) {
            actionIcon = 'fa-sign-out-alt';
            actionClass = 'text-secondary';
        }
        
        return `
        <tr>
            <td>${formatDateTime(log.timestamp)}</td>
            <td><strong>${escapeHtml(log.user_name || 'System')}</strong></td>
            <td>
                <span class="${actionClass}">
                    <i class="fas ${actionIcon}"></i> ${escapeHtml(log.action.replace(/_/g, ' '))}
                </span>
            </td>
            <td>${escapeHtml(log.target_type || '')}${log.target_id ? ' #' + log.target_id : ''}</td>
            <td class="audit-details-cell">${detailsHtml}</td>
        </tr>
        `;
    }).join('');
    
    // Insert floating panels into the document body (YouTube-style side panel)
    panelsToInsert.forEach(html => {
        document.body.insertAdjacentHTML('beforeend', html);
    });
    
    // Attach event listeners after rendering (only once globally)
    attachAuditHoverListeners();
}

// Helper function to format JSON for better display with syntax highlighting
function formatJsonForDisplay(obj) {
    if (!obj) return 'null';
    let jsonStr = JSON.stringify(obj, null, 2);
    
    // Escape HTML first
    jsonStr = escapeHtml(jsonStr);
    
    // Add syntax highlighting (simple approach)
    jsonStr = jsonStr
        .replace(/("([^"\\]|\\.)*")\s*:/g, '<span class="json-key">$1</span>:')
        .replace(/:\s*("([^"\\]|\\.)*")/g, ': <span class="json-string">$1</span>')
        .replace(/:\s*(\d+\.?\d*)/g, ': <span class="json-number">$1</span>')
        .replace(/:\s*(true|false|null)\b/g, ': <span class="json-boolean">$1</span>');
    
    return jsonStr;
}

// Helper function to get changed fields between before and after
function getChangedFields(before, after) {
    const changes = [];
    const allKeys = new Set([...Object.keys(before || {}), ...Object.keys(after || {})]);
    
    allKeys.forEach(key => {
        const beforeVal = before?.[key];
        const afterVal = after?.[key];
        if (JSON.stringify(beforeVal) !== JSON.stringify(afterVal)) {
            changes.push({
                field: key,
                before: beforeVal,
                after: afterVal
            });
        }
    });
    
    return changes;
}

// Helper function to format values for display (old, kept for compatibility)
function formatValue(val) {
    return formatValueForDisplay(val);
}

// Helper function to format values for human-readable display
function formatValueForDisplay(val) {
    if (val === null || val === undefined) return '<span class="audit-null-value">—</span>';
    if (val === '') return '<span class="audit-empty-value">(empty)</span>';
    
    // Handle dates
    if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}/.test(val)) {
        try {
            const date = new Date(val);
            if (!isNaN(date.getTime())) {
                return `<span class="audit-date-value">${formatDateTime(val)}</span>`;
            }
        } catch (e) {}
    }
    
    // Handle booleans
    if (typeof val === 'boolean') {
        return `<span class="audit-bool-value audit-bool-${val}">${val ? 'Yes' : 'No'}</span>`;
    }
    
    // Handle numbers
    if (typeof val === 'number') {
        // Format large numbers with commas
        if (val >= 1000) {
            return `<span class="audit-number-value">${val.toLocaleString()}</span>`;
        }
        return `<span class="audit-number-value">${val}</span>`;
    }
    
    // Handle objects/arrays
    if (typeof val === 'object') {
        if (Array.isArray(val)) {
            if (val.length === 0) return '<span class="audit-empty-value">(empty array)</span>';
            return `<span class="audit-array-value">[${val.length} items]</span>`;
        }
        const keys = Object.keys(val);
        if (keys.length === 0) return '<span class="audit-empty-value">(empty object)</span>';
        return `<span class="audit-object-value">{${keys.length} fields}</span>`;
    }
    
    // Handle strings
    if (typeof val === 'string') {
        const maxLength = 60;
        if (val.length > maxLength) {
            return `<span class="audit-string-value" title="${escapeHtml(val)}">${escapeHtml(val.substring(0, maxLength))}...</span>`;
        }
        return `<span class="audit-string-value">${escapeHtml(val)}</span>`;
    }
    
    return escapeHtml(String(val));
}

// Helper function to format field labels (convert snake_case to Title Case)
function formatFieldLabel(field) {
    return field
        .replace(/_/g, ' ')
        .replace(/\b\w/g, l => l.toUpperCase())
        .replace(/Id\b/g, 'ID')
        .replace(/Url\b/g, 'URL')
        .replace(/Email\b/g, 'Email')
        .replace(/Phone\b/g, 'Phone');
}

// Click to show audit details
let auditGlobalListenersAttached = false;
let currentlyOpenPanel = null;

function attachAuditHoverListeners() {
    // Use global event delegation - only attach once
    if (auditGlobalListenersAttached) return;
    auditGlobalListenersAttached = true;
    
    // Global click handler for trigger
    document.addEventListener('click', (e) => {
        // Ensure e.target is an Element
        if (!e.target || typeof e.target.closest !== 'function') return;
        
        // Check for pin button click
        const pinBtn = e.target.closest('.audit-pin-btn');
        if (pinBtn) {
            e.stopPropagation();
            const rowId = pinBtn.getAttribute('data-row-id');
            if (rowId) {
                pinAuditDetails(rowId);
            }
            return;
        }
        
        // Check for trigger click
        const trigger = e.target.closest('.audit-hover-trigger');
        if (trigger) {
            e.stopPropagation();
            const container = trigger.closest('.audit-hover-container');
            if (container) {
                const rowId = container.getAttribute('data-row-id');
                if (rowId) {
                    toggleAuditDetails(rowId);
                }
            }
            return;
        }
        
        // Check for backdrop click (close panel)
        if (e.target.id === 'audit-backdrop') {
            const rowId = e.target.getAttribute('data-row-id');
            if (rowId) {
                const detailsEl = document.getElementById(`auditDetails${rowId}`);
                if (detailsEl && detailsEl.getAttribute('data-pinned') !== 'true') {
                    hideAuditDetails(rowId);
                }
            }
        }
    }, true);
}

function toggleAuditDetails(rowId) {
    const detailsEl = document.getElementById(`auditDetails${rowId}`);
    if (!detailsEl) {
        console.warn('Panel not found for rowId:', rowId);
        return;
    }
    
    // If this panel is already open, close it
    if (currentlyOpenPanel === rowId && detailsEl.classList.contains('audit-panel-visible')) {
        hideAuditDetails(rowId);
        return;
    }
    
    // Close any other open panel first
    if (currentlyOpenPanel && currentlyOpenPanel !== rowId) {
        const otherPanel = document.getElementById(`auditDetails${currentlyOpenPanel}`);
        if (otherPanel && otherPanel.getAttribute('data-pinned') !== 'true') {
            hideAuditDetails(currentlyOpenPanel);
        }
    }
    
    // Show this panel
    detailsEl.classList.add('audit-panel-visible');
    currentlyOpenPanel = rowId;
    addAuditBackdrop(rowId);
}

function showAuditDetails(rowId) {
    const detailsEl = document.getElementById(`auditDetails${rowId}`);
    if (!detailsEl) {
        console.warn('Panel not found for rowId:', rowId);
        return;
    }
    
    // Close any other open panel first
    if (currentlyOpenPanel && currentlyOpenPanel !== rowId) {
        const otherPanel = document.getElementById(`auditDetails${currentlyOpenPanel}`);
        if (otherPanel && otherPanel.getAttribute('data-pinned') !== 'true') {
            hideAuditDetails(currentlyOpenPanel);
        }
    }
    
    detailsEl.classList.add('audit-panel-visible');
    currentlyOpenPanel = rowId;
    addAuditBackdrop(rowId);
}

function addAuditBackdrop(rowId) {
    // Remove any existing backdrop
    const existing = document.getElementById('audit-backdrop');
    if (existing) existing.remove();
    
    // Create backdrop overlay
    const backdrop = document.createElement('div');
    backdrop.id = 'audit-backdrop';
    backdrop.className = 'audit-backdrop';
    backdrop.setAttribute('data-row-id', rowId);
    
    document.body.appendChild(backdrop);
}

function removeAuditBackdrop() {
    const backdrop = document.getElementById('audit-backdrop');
    if (backdrop) backdrop.remove();
}

function hideAuditDetails(rowId) {
    const detailsEl = document.getElementById(`auditDetails${rowId}`);
    if (!detailsEl) return;
    
    // Check if pinned - if pinned, don't hide
    const isPinned = detailsEl.getAttribute('data-pinned') === 'true';
    if (isPinned) return;
    
    // Hide the panel
    detailsEl.classList.remove('audit-panel-visible');
    removeAuditBackdrop();
    
    // Clear currently open panel if this was it
    if (currentlyOpenPanel === rowId) {
        currentlyOpenPanel = null;
    }
}

function pinAuditDetails(rowId) {
    const detailsEl = document.getElementById(`auditDetails${rowId}`);
    if (!detailsEl) return;
    
    const isPinned = detailsEl.getAttribute('data-pinned') === 'true';
    const pinBtn = detailsEl.querySelector('.audit-pin-btn');
    const pinIcon = pinBtn?.querySelector('i');
    // Find container using the rowId (panel might be in body now)
    const container = document.querySelector(`.audit-hover-container[data-row-id="${rowId}"]`);
    const trigger = container?.querySelector('.audit-hover-trigger');
    
    if (isPinned) {
        // Unpin - hide panel but keep trigger visible
        detailsEl.setAttribute('data-pinned', 'false');
        detailsEl.classList.remove('audit-panel-visible');
        removeAuditBackdrop();
        if (pinIcon) {
            pinIcon.style.transform = 'rotate(0deg)';
            pinBtn.classList.remove('pinned');
        }
        // Clear currently open panel if this was it
        if (currentlyOpenPanel === rowId) {
            currentlyOpenPanel = null;
        }
        // Ensure trigger remains visible
        if (trigger) {
            trigger.style.display = 'inline-flex';
            trigger.style.visibility = 'visible';
            trigger.style.opacity = '1';
        }
    } else {
        // Pin - show panel and keep it visible
        detailsEl.setAttribute('data-pinned', 'true');
        detailsEl.classList.add('audit-panel-visible');
        currentlyOpenPanel = rowId;
        addAuditBackdrop(rowId);
        if (pinIcon) {
            pinIcon.style.transform = 'rotate(45deg)';
            pinBtn.classList.add('pinned');
        }
        // Ensure trigger remains visible
        if (trigger) {
            trigger.style.display = 'inline-flex';
            trigger.style.visibility = 'visible';
            trigger.style.opacity = '1';
        }
    }
}

function searchAuditTrail() {
    const searchTerm = document.getElementById('auditSearch').value.toLowerCase();
    const actionFilter = document.getElementById('auditActionFilter').value;
    const targetFilter = document.getElementById('auditTargetFilter').value;
    
    let filtered = allAuditTrailData.filter(log => {
        // Search filter
        const matchesSearch = !searchTerm || 
            (log.user_name && log.user_name.toLowerCase().includes(searchTerm)) ||
            (log.action && log.action.toLowerCase().includes(searchTerm)) ||
            (log.target_type && log.target_type.toLowerCase().includes(searchTerm)) ||
            (log.target_id && log.target_id.toString().includes(searchTerm));
        
        // Action filter
        const matchesAction = !actionFilter || 
            (actionFilter === 'create' && log.action.includes('create')) ||
            (actionFilter === 'update' && log.action.includes('update')) ||
            (actionFilter === 'delete' && (log.action.includes('delete') || log.action.includes('cancel'))) ||
            (actionFilter === 'login' && log.action.includes('login')) ||
            (actionFilter === 'logout' && log.action.includes('logout'));
        
        // Target filter
        const matchesTarget = !targetFilter || 
            (log.target_type && log.target_type.toLowerCase().includes(targetFilter));
        
        return matchesSearch && matchesAction && matchesTarget;
    });
    
    displayAuditTrail(filtered);
}

function filterAuditTrail() {
    searchAuditTrail(); // Reuse search function for filtering
}

let auditTrailSortOrder = {};
function sortAuditTrail(column) {
    const order = auditTrailSortOrder[column] === 'asc' ? 'desc' : 'asc';
    auditTrailSortOrder[column] = order;
    
    const sorted = [...allAuditTrailData].sort((a, b) => {
        let aVal = a[column];
        let bVal = b[column];
        
        if (column === 'timestamp') {
            aVal = new Date(a.timestamp);
            bVal = new Date(b.timestamp);
        } else if (aVal && typeof aVal === 'string') {
            aVal = aVal.toLowerCase();
        }
        if (bVal && typeof bVal === 'string') {
            bVal = bVal.toLowerCase();
        }
        
        if (aVal < bVal) return order === 'asc' ? -1 : 1;
        if (aVal > bVal) return order === 'asc' ? 1 : -1;
        return 0;
    });
    
    displayAuditTrail(sorted);
}

function searchUsers() {
    const searchTerm = document.getElementById('userSearch').value;
    const roleFilter = document.getElementById('userRoleFilter').value;
    
    let url = API_BASE + 'users.php';
    const params = [];
    
    if (searchTerm) params.push(`search=${encodeURIComponent(searchTerm)}`);
    if (roleFilter) params.push(`role=${encodeURIComponent(roleFilter)}`);
    
    if (params.length > 0) {
        url += '?' + params.join('&');
    }
    
    fetch(url)
        .then(response => response.json())
        .then(users => displayUsers(users || []))
        .catch(err => showAlert('Search error: ' + err.message, 'error'));
}

function filterUsers() {
    searchUsers();
}

function showUserModal(userId = null) {
    currentEditId = userId;
    document.getElementById('userModalTitle').textContent = userId ? 'Edit User' : 'Add User';
    document.getElementById('userSaveBtn').textContent = userId ? 'Update User' : 'Save User';
    document.getElementById('passwordLabel').style.display = userId ? 'inline' : 'none';
    
    const modal = document.getElementById('userModal');
    modal.classList.remove('hidden');
    setTimeout(() => modal.classList.add('show'), 10);
    
    // Clear form
    document.getElementById('userForm').reset();
    
    if (userId) {
        loadUserDetails(userId);
    } else {
        // Make password required for new users
        document.getElementById('userPassword').required = true;
    }
}

async function loadUserDetails(id) {
    try {
        const response = await fetch(API_BASE + `users.php?id=${id}`);
        const user = await response.json();
        
        if (user) {
            document.getElementById('userName').value = user.name || '';
            document.getElementById('userEmail').value = user.email || '';
            document.getElementById('userRole').value = user.role || 'staff';
            document.getElementById('userPhone').value = user.phone || '';
            document.getElementById('userNotes').value = user.notes || '';
            // Password field stays empty - optional for edit
            document.getElementById('userPassword').required = false;
        }
    } catch (err) {
        showAlert('Error loading user details: ' + err.message, 'error');
    }
}

async function saveUser(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    // Remove password if empty (for edit)
    if (!data.password) {
        delete data.password;
    }
    
    try {
        const url = currentEditId 
            ? API_BASE + `users.php?id=${currentEditId}`
            : API_BASE + 'users.php';
        
        const response = await fetch(url, {
            method: currentEditId ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Failed to save user');
        }
        
        const result = await response.json();
        hideModal();
        loadUsers();
        showToast(
            currentEditId ? 'User Updated' : 'User Added',
            currentEditId ? `${result.name} has been updated` : `${result.name} has been added to the system`,
            'success'
        );
    } catch (err) {
        showAlert('Error saving user: ' + err.message, 'error');
    }
}

function editUser(id) {
    showUserModal(id);
}

async function deleteUser(id) {
    if (!confirm('Are you sure you want to delete this user?')) return;
    
    try {
        const response = await fetch(API_BASE + `users.php?id=${id}`, {
            method: 'DELETE'
        });
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Failed to delete user');
        }
        
        loadUsers();
        showToast('User Deleted', 'User account has been removed from the system', 'success');
    } catch (err) {
        showAlert('Error deleting user: ' + err.message, 'error');
    }
}
async function loadDashboard() {
    try {
        const stats = await apiFetch(API_BASE + 'reports.php?type=dashboard');
        
        document.getElementById('totalPatients').textContent = stats.total_patients || 0;
        document.getElementById('newThisMonth').textContent = stats.new_patients_month || 0;
        document.getElementById('totalAppointments').textContent = stats.total_appointments || 0;
        document.getElementById('pendingAppointments').textContent = stats.pending_appointments || 0;
        document.getElementById('totalRevenue').textContent = formatCurrency(stats.total_revenue || 0);
        document.getElementById('activeDoctors').textContent = stats.active_doctors || 0;
    } catch (err) {
        console.error('Error loading dashboard:', err);
        showAlert('Error loading dashboard: ' + err.message, 'error');
        
        // Set default values
        document.getElementById('totalPatients').textContent = '0';
        document.getElementById('newThisMonth').textContent = '0';
        document.getElementById('totalAppointments').textContent = '0';
        document.getElementById('pendingAppointments').textContent = '0';
        document.getElementById('totalRevenue').textContent = formatCurrency(0);
        document.getElementById('activeDoctors').textContent = '0';
    }
}

// Patient Functions
async function loadPatients() {
    try {
        const response = await fetch(API_BASE + 'patients.php');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        // Ensure data is an array
        const patients = Array.isArray(data) ? data : [];
        displayPatients(patients);
    } catch (err) {
        console.error('Error loading patients:', err);
        showAlert('Error loading patients: ' + err.message, 'error');
        displayPatients([]); // Show empty state
    }
}

function displayPatients(patients) {
    const tbody = document.querySelector('#patientsTable tbody');
    
    if (patients.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="empty-state">
                    <div class="empty-state-icon"><i class="fas fa-users"></i></div>
                    <div class="empty-state-text">No patients found</div>
                    <div class="empty-state-subtext">Add your first patient to get started</div>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = patients.map(p => `
        <tr>
            <td>
                <div style="font-weight: 500;">${escapeHtml(p.first_name)} ${escapeHtml(p.last_name)}</div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">ID: ${p.id}</div>
                ${p.blood_type || p.allergies ? `
                <div style="margin-top: 0.5rem; display: flex; gap: 0.25rem; flex-wrap: wrap;">
                    ${p.blood_type ? `<span style="background: #fee2e2; color: #991b1b; padding: 0.125rem 0.5rem; border-radius: 0.25rem; font-size: 0.7rem; font-weight: 500;">${escapeHtml(p.blood_type)}</span>` : ''}
                    ${p.allergies ? `<span style="background: #fef3c7; color: #92400e; padding: 0.125rem 0.5rem; border-radius: 0.25rem; font-size: 0.7rem; font-weight: 500;">ALLERGIES</span>` : ''}
                </div>
                ` : ''}
            </td>
            <td>${formatDate(p.dob)}</td>
            <td>${escapeHtml(p.gender || 'Not specified')}</td>
            <td>${escapeHtml(p.phone || 'Not provided')}</td>
            <td>${escapeHtml(p.email || 'Not provided')}</td>
            <td class="action-buttons">
                <button class="btn btn-sm btn-primary" onclick="viewPatientDetails(${p.id})">
                    View
                </button>
                ${hasPermission('patients', 'edit') ? `
                <button class="btn btn-sm btn-secondary" onclick="editPatient(${p.id})">
                    Edit
                </button>
                ` : ''}
                ${hasPermission('patients', 'delete') ? `
                <button class="btn btn-sm btn-danger" onclick="deletePatient(${p.id})">
                    Delete
                </button>
                ` : ''}
            </td>
        </tr>
    `).join('');
}

function searchPatients() {
    const searchTerm = document.getElementById('patientSearch').value;
    const genderFilter = document.getElementById('genderFilter').value;
    
    let url = API_BASE + 'patients.php';
    const params = [];
    
    if (searchTerm) params.push(`search=${encodeURIComponent(searchTerm)}`);
    if (genderFilter) params.push(`gender=${encodeURIComponent(genderFilter)}`);
    
    if (params.length > 0) {
        url += '?' + params.join('&');
    }
    
    fetch(url)
        .then(response => response.json())
        .then(patients => displayPatients(patients || []))
        .catch(err => showAlert('Search error: ' + err.message, 'error'));
}

function filterPatients() {
    searchPatients();
}

function sortPatients(field) {
    // Implementation for sorting patients
    loadPatients(); // For now, just reload
}

function showPatientModal(patientId = null) {
    currentEditId = patientId;
    document.getElementById('patientModalTitle').textContent = patientId ? 'Edit Patient' : 'Add Patient';
    document.getElementById('patientSaveBtn').textContent = patientId ? 'Update Patient' : 'Save Patient';
    
    const modal = document.getElementById('patientModal');
    modal.classList.remove('hidden');
    setTimeout(() => modal.classList.add('show'), 10);
    
    if (patientId) {
        loadPatientDetails(patientId);
    }
}

async function loadPatientDetails(id) {
    try {
        const response = await fetch(API_BASE + `patients.php?id=${id}`);
        const patient = await response.json();
        
        if (patient) {
            document.getElementById('patientFirstName').value = patient.first_name || '';
            document.getElementById('patientLastName').value = patient.last_name || '';
            document.getElementById('patientDob').value = patient.dob || '';
            document.getElementById('patientGender').value = patient.gender || '';
            document.getElementById('patientAddress').value = patient.address || '';
            document.getElementById('patientPhone').value = patient.phone || '';
            document.getElementById('patientEmail').value = patient.email || '';
            document.getElementById('patientEmergencyContact').value = patient.emergency_contact || '';
            document.getElementById('patientEmergencyPhone').value = patient.emergency_phone || '';
            document.getElementById('patientInsuranceProvider').value = patient.insurance_provider || '';
            document.getElementById('patientInsuranceNumber').value = patient.insurance_number || '';
            document.getElementById('patientBloodType').value = patient.blood_type || '';
            document.getElementById('patientAllergies').value = patient.allergies || '';
            document.getElementById('patientMedicalHistory').value = patient.medical_history || '';
            document.getElementById('patientNotes').value = patient.notes || '';
        }
    } catch (err) {
        showAlert('Error loading patient details: ' + err.message, 'error');
    }
}

async function savePatient(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    try {
        const url = currentEditId 
            ? API_BASE + `patients.php?id=${currentEditId}`
            : API_BASE + 'patients.php';
        
        const response = await fetch(url, {
            method: currentEditId ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        if (!response.ok) throw new Error('Failed to save patient');
        
        const result = await response.json();
        hideModal();
        loadPatients();
        showToast(
            currentEditId ? 'Patient Updated' : 'Patient Added',
            currentEditId ? `${result.first_name} ${result.last_name} has been updated` : `${result.first_name} ${result.last_name} has been added to the system`,
            'success'
        );
    } catch (err) {
        showAlert('Error saving patient: ' + err.message, 'error');
    }
}

async function deletePatient(id) {
    if (!confirm('Are you sure you want to delete this patient?')) return;
    
    try {
        const response = await fetch(API_BASE + `patients.php?id=${id}`, {
            method: 'DELETE'
        });
        
        if (!response.ok) throw new Error('Failed to delete patient');
        
        loadPatients();
        showToast('Patient Deleted', 'Patient record has been removed from the system', 'success');
    } catch (err) {
        showAlert('Error deleting patient: ' + err.message, 'error');
    }
}

function editPatient(id) {
    showPatientModal(id);
}

// View Patient Details
async function viewPatientDetails(id) {
    try {
        const [patientResponse, recordsResponse] = await Promise.all([
            fetch(API_BASE + `patients.php?id=${id}`),
            fetch(API_BASE + `medical_records.php?patient_id=${id}`)
        ]);

        const patient = await patientResponse.json();
        if (!patient) {
            showAlert('Patient not found', 'error');
            return;
        }

        let medicalRecords = [];
        if (recordsResponse.ok) {
            const recordsData = await recordsResponse.json();
            medicalRecords = Array.isArray(recordsData) ? recordsData : [];
        } else {
            console.warn('Failed to load medical records for patient', id);
        }
        
        const modal = document.getElementById('patientDetailsModal');
        modal.classList.remove('hidden');
        setTimeout(() => modal.classList.add('show'), 10);
        
        displayPatientDetailsInModal(patient, medicalRecords);
    } catch (err) {
        console.error('Error loading patient details', err);
        showAlert('Error loading patient details: ' + err.message, 'error');
    }
}

function displayPatientDetailsInModal(p, medicalRecords = []) {
    const content = document.getElementById('patientDetailsContent');
    
    const age = p.dob ? calculateAge(p.dob) : 'N/A';
    const canAddRecords = typeof hasPermission === 'function' ? hasPermission('medical_records', 'add') : false;
    const canViewRecords = typeof hasPermission === 'function' ? hasPermission('medical_records', 'view') : false;
    const recentRecords = (medicalRecords || []).sort((a, b) => new Date(b.created_at) - new Date(a.created_at)).slice(0, 4);
    
    content.innerHTML = `
        <!-- Patient Header -->
        <div style="background: linear-gradient(135deg, var(--primary), var(--info)); color: white; padding: 2rem; margin: -2rem -2rem 2rem -2rem; border-radius: var(--radius-lg) var(--radius-lg) 0 0;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 80px; height: 80px; background: rgba(255,255,255,0.3); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; border: 3px solid white;">
                    ${p.first_name ? p.first_name.charAt(0).toUpperCase() : 'P'}${p.last_name ? p.last_name.charAt(0).toUpperCase() : ''}
                </div>
                <div style="flex: 1;">
                    <h2 style="margin: 0; font-size: 1.75rem; font-weight: 700;">${escapeHtml(p.first_name)} ${escapeHtml(p.last_name)}</h2>
                    <div style="margin-top: 0.5rem; font-size: 0.875rem; opacity: 0.9;">
                        Patient ID: ${p.id} | ${p.gender || 'Not specified'} | ${age} years old
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Critical Medical Information -->
        ${p.blood_type || p.allergies ? `
        <div style="background: #fef3c7; border: 2px solid #f59e0b; border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                <span style="font-size: 1rem; font-weight: 700; color: #92400e; text-transform: uppercase; letter-spacing: 0.05em;">⚠ WARNING</span>
                <h3 style="margin: 0; color: #92400e; font-size: 1.125rem; font-weight: 700;">Critical Medical Information</h3>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                ${p.blood_type ? `
                <div>
                    <div style="font-weight: 600; color: #92400e; margin-bottom: 0.25rem; font-size: 0.875rem;">BLOOD TYPE</div>
                    <div style="background: #fee2e2; color: #991b1b; padding: 0.5rem 1rem; border-radius: var(--radius); font-weight: 700; font-size: 1.25rem; display: inline-block;">
                        ${escapeHtml(p.blood_type)}
                    </div>
                </div>
                ` : ''}
                ${p.allergies ? `
                <div>
                    <div style="font-weight: 600; color: #92400e; margin-bottom: 0.25rem; font-size: 0.875rem;">ALLERGIES</div>
                    <div style="background: white; color: #991b1b; padding: 0.75rem; border-radius: var(--radius); font-weight: 600; border: 2px solid #fca5a5;">
                        ${escapeHtml(p.allergies)}
                    </div>
                </div>
                ` : ''}
            </div>
        </div>
        ` : ''}
        
        <!-- Demographics Section -->
        <div style="margin-bottom: 1.5rem;">
            <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--border-light);">Demographics</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Date of Birth</div>
                    <div style="font-weight: 500;">${formatDate(p.dob)}</div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Gender</div>
                    <div style="font-weight: 500;">${escapeHtml(p.gender || 'Not specified')}</div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Phone</div>
                    <div style="font-weight: 500;">${escapeHtml(p.phone || 'Not provided')}</div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Email</div>
                    <div style="font-weight: 500;">${escapeHtml(p.email || 'Not provided')}</div>
                </div>
            </div>
            ${p.address ? `
            <div style="margin-top: 1rem;">
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Address</div>
                <div style="font-weight: 500;">${escapeHtml(p.address)}</div>
            </div>
            ` : ''}
        </div>
        
        <!-- Emergency Contact -->
        ${p.emergency_contact || p.emergency_phone ? `
        <div style="margin-bottom: 1.5rem;">
            <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--border-light);">Emergency Contact</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                ${p.emergency_contact ? `
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Contact Name</div>
                    <div style="font-weight: 500;">${escapeHtml(p.emergency_contact)}</div>
                </div>
                ` : ''}
                ${p.emergency_phone ? `
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Contact Phone</div>
                    <div style="font-weight: 500;">${escapeHtml(p.emergency_phone)}</div>
                </div>
                ` : ''}
            </div>
        </div>
        ` : ''}
        
        <!-- Insurance Information -->
        ${p.insurance_provider || p.insurance_number ? `
        <div style="margin-bottom: 1.5rem;">
            <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--border-light);">Insurance Information</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                ${p.insurance_provider ? `
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Provider</div>
                    <div style="font-weight: 500;">${escapeHtml(p.insurance_provider)}</div>
                </div>
                ` : ''}
                ${p.insurance_number ? `
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Policy Number</div>
                    <div style="font-weight: 500;">${escapeHtml(p.insurance_number)}</div>
                </div>
                ` : ''}
            </div>
        </div>
        ` : ''}
        
        <!-- Medical History -->
        ${p.medical_history ? `
        <div style="margin-bottom: 1.5rem;">
            <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--border-light);">Medical History</h3>
            <div style="background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius); white-space: pre-wrap;">${escapeHtml(p.medical_history)}</div>
        </div>
        ` : ''}
        
        <!-- Additional Notes -->
        ${p.notes ? `
        <div style="margin-bottom: 1.5rem;">
            <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--border-light);">Additional Notes</h3>
            <div style="background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius); white-space: pre-wrap;">${escapeHtml(p.notes)}</div>
        </div>
        ` : ''}

        <!-- Medical Records Overview -->
        ${canViewRecords ? `
        <div style="margin-bottom: 1.5rem;">
            <div style="display:flex; align-items:center; justify-content: space-between; margin-bottom: 1rem;">
                <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-primary); margin:0;">Medical Records</h3>
                <div style="display:flex; gap:0.5rem;">
                    ${canAddRecords ? `<button class="btn btn-sm btn-primary" onclick="openRecordModalForPatient(${p.id})">Add Record</button>` : ''}
                    <button class="btn btn-sm btn-secondary" onclick="navigateToMedicalRecordsSection(${p.id})">View All</button>
                </div>
            </div>
            ${recentRecords.length > 0 ? `
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                ${recentRecords.map(record => `
                    <div class="card" style="padding: 1rem; border: 1px solid var(--border-light); border-radius: var(--radius); display:flex; justify-content: space-between; align-items: center; gap:1rem;">
                        <div>
                            <div style="font-size:0.75rem; text-transform:uppercase; font-weight:600; color: var(--text-muted);">${escapeHtml((record.record_type || '').replace('_',' ') || 'Record')}</div>
                            <div style="font-weight:600; font-size:1rem;">${escapeHtml(record.title || 'Untitled record')}</div>
                            <div style="font-size:0.85rem; color: var(--text-muted);">${formatDateTime(record.created_at)}</div>
                        </div>
                        <div style="display:flex; gap:0.5rem;">
                            <button class="btn btn-sm btn-secondary" onclick="viewRecord(${record.id})">View</button>
                        </div>
                    </div>
                `).join('')}
            </div>
            ` : `
            <div style="padding:1.25rem; border:1px dashed var(--border-light); border-radius: var(--radius); text-align:center; color: var(--text-muted);">
                No medical records yet for this patient.
                ${canAddRecords ? `<div style="margin-top:0.5rem;"><button class=\"btn btn-sm btn-primary\" onclick=\"openRecordModalForPatient(${p.id})\">Add the first record</button></div>` : ''}
            </div>
            `}
        </div>
        ` : ''}
        
        <!-- Action Buttons -->
        <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem; padding-top: 1.5rem; border-top: 2px solid var(--border-light);">
            ${hasPermission('patients', 'edit') ? `
            <button class="btn btn-primary" onclick="closePatientDetailsAndEdit(${p.id})">
                Edit Patient
            </button>
            ` : ''}
            <button class="btn btn-secondary" onclick="closePatientDetails()">
                Close
            </button>
        </div>
    `;
}

function calculateAge(dob) {
    if (!dob) return 'N/A';
    const birthDate = new Date(dob);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    return age;
}

function closePatientDetails() {
    const modal = document.getElementById('patientDetailsModal');
    modal.classList.remove('show');
    setTimeout(() => modal.classList.add('hidden'), 300);
}

function navigateToMedicalRecordsSection(patientId) {
    closePatientDetails();
    showSection('medical-records');
    const patientFilter = document.getElementById('recordPatientFilter');
    if (patientFilter) {
        patientFilter.value = patientId;
        filterRecords();
    }
}

function openRecordModalForPatient(patientId) {
    showRecordModal();
    setTimeout(() => {
        const patientSelect = document.getElementById('recordPatient');
        if (patientSelect) {
            patientSelect.value = patientId;
            patientSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }, 250);
}

function closePatientDetailsAndEdit(id) {
    closePatientDetails();
    setTimeout(() => editPatient(id), 400);
}

// Doctor Functions
async function loadDoctors() {
    try {
        const response = await fetch(API_BASE + 'doctors.php');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        const doctors = Array.isArray(data) ? data : [];
        displayDoctors(doctors);
    } catch (err) {
        console.error('Error loading doctors:', err);
        showAlert('Error loading doctors: ' + err.message, 'error');
        displayDoctors([]);
    }
}

function displayDoctors(doctors) {
    const tbody = document.querySelector('#doctorsTable tbody');
    
    if (doctors.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="empty-state">
                    <div class="empty-state-text">No doctors found</div>
                    <div class="empty-state-subtext">Add your first doctor to get started</div>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = doctors.map(d => `
        <tr>
            <td>
                <div style="font-weight: 500;">${escapeHtml(d.first_name)} ${escapeHtml(d.last_name)}</div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">ID: ${d.id}</div>
            </td>
            <td>${escapeHtml(d.specialty || 'Not specified')}</td>
            <td>${escapeHtml(d.phone || 'Not provided')}</td>
            <td>${escapeHtml(d.email || 'Not provided')}</td>
            <td>${escapeHtml(d.license_number || 'Not provided')}</td>
            <td class="action-buttons">
                ${hasPermission('doctors', 'edit') ? `
                <button class="btn btn-sm btn-secondary" onclick="editDoctor(${d.id})">
                    Edit
                </button>
                ` : ''}
                ${hasPermission('doctors', 'delete') ? `
                <button class="btn btn-sm btn-danger" onclick="deleteDoctor(${d.id})">
                    Delete
                </button>
                ` : ''}
            </td>
        </tr>
    `).join('');
}

function searchDoctors() {
    const searchTerm = document.getElementById('doctorSearch').value;
    const specialtyFilter = document.getElementById('specialtyFilter').value;
    
    let url = API_BASE + 'doctors.php';
    const params = [];
    
    if (searchTerm) params.push(`search=${encodeURIComponent(searchTerm)}`);
    if (specialtyFilter) params.push(`specialty=${encodeURIComponent(specialtyFilter)}`);
    
    if (params.length > 0) {
        url += '?' + params.join('&');
    }
    
    fetch(url)
        .then(response => response.json())
        .then(doctors => displayDoctors(doctors || []))
        .catch(err => showAlert('Search error: ' + err.message, 'error'));
}

function filterDoctors() {
    searchDoctors();
}

function showDoctorModal(doctorId = null) {
    currentEditId = doctorId;
    document.getElementById('doctorModalTitle').textContent = doctorId ? 'Edit Doctor' : 'Add Doctor';
    document.getElementById('doctorSaveBtn').textContent = doctorId ? 'Update Doctor' : 'Save Doctor';
    
    const modal = document.getElementById('doctorModal');
    modal.classList.remove('hidden');
    setTimeout(() => modal.classList.add('show'), 10);
    
    // Clear form
    document.getElementById('doctorForm').reset();
    
    if (doctorId) {
        loadDoctorDetails(doctorId);
    }
}

async function loadDoctorDetails(id) {
    try {
        const response = await fetch(API_BASE + `doctors.php?id=${id}`);
        const doctor = await response.json();
        
        if (doctor) {
            document.getElementById('doctorFirstName').value = doctor.first_name || '';
            document.getElementById('doctorLastName').value = doctor.last_name || '';
            document.getElementById('doctorSpecialty').value = doctor.specialty || '';
            document.getElementById('doctorLicenseNumber').value = doctor.license_number || '';
            document.getElementById('doctorPhone').value = doctor.phone || '';
            document.getElementById('doctorEmail').value = doctor.email || '';
            document.getElementById('doctorNotes').value = doctor.notes || '';
        }
    } catch (err) {
        showAlert('Error loading doctor details: ' + err.message, 'error');
    }
}

async function saveDoctor(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    try {
        const url = currentEditId 
            ? API_BASE + `doctors.php?id=${currentEditId}`
            : API_BASE + 'doctors.php';
        
        const response = await fetch(url, {
            method: currentEditId ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Failed to save doctor');
        }
        
        const result = await response.json();
        hideModal();
        loadDoctors();
        showToast(
            currentEditId ? 'Doctor Updated' : 'Doctor Added',
            `Dr. ${result.first_name} ${result.last_name} has been ${currentEditId ? 'updated' : 'added to the system'}`,
            'success'
        );
    } catch (err) {
        showAlert('Error saving doctor: ' + err.message, 'error');
    }
}

function editDoctor(id) {
    showDoctorModal(id);
}

async function deleteDoctor(id) {
    if (!confirm('Are you sure you want to delete this doctor?')) return;
    
    try {
        const response = await fetch(API_BASE + `doctors.php?id=${id}`, {
            method: 'DELETE'
        });
        
        if (!response.ok) throw new Error('Failed to delete doctor');
        
        loadDoctors();
        showToast('Doctor Deleted', 'Doctor record has been removed from the system', 'success');
    } catch (err) {
        showAlert('Error deleting doctor: ' + err.message, 'error');
    }
}

// Room Functions
async function loadRooms() {
    try {
        const response = await fetch(API_BASE + 'rooms.php');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        const rooms = Array.isArray(data) ? data : [];
        displayRooms(rooms);
    } catch (err) {
        console.error('Error loading rooms:', err);
        showAlert('Error loading rooms: ' + err.message, 'error');
        displayRooms([]);
    }
}

function displayRooms(rooms) {
    const tbody = document.querySelector('#roomsTable tbody');
    
    if (rooms.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="empty-state">
                    <div class="empty-state-text">No rooms found</div>
                    <div class="empty-state-subtext">Add your first room to get started</div>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = rooms.map(r => `
        <tr>
            <td>
                <div style="font-weight: 500;">${escapeHtml(r.room_number)}</div>
            </td>
            <td>${escapeHtml(r.room_name)}</td>
            <td><span style="text-transform: capitalize;">${escapeHtml(r.room_type)}</span></td>
            <td>${escapeHtml(r.floor || 'N/A')}</td>
            <td>${r.capacity}</td>
            <td>
                <span class="status-badge status-${r.is_available == 1 ? 'completed' : r.is_available == 2 ? 'scheduled' : 'cancelled'}">
                    ${r.is_available == 1 ? 'Available' : r.is_available == 2 ? 'Occupied' : 'Unavailable'}
                </span>
            </td>
            <td class="action-buttons">
                ${hasPermission('rooms', 'edit') ? `
                <button class="btn btn-sm btn-secondary" onclick="editRoom(${r.id})">
                    Edit
                </button>
                ` : ''}
                ${hasPermission('rooms', 'delete') ? `
                <button class="btn btn-sm btn-danger" onclick="deleteRoom(${r.id})">
                    Delete
                </button>
                ` : ''}
            </td>
        </tr>
    `).join('');
}

function searchRooms() {
    const searchTerm = document.getElementById('roomSearch').value;
    const typeFilter = document.getElementById('roomTypeFilter').value;
    const availabilityFilter = document.getElementById('roomAvailabilityFilter').value;
    
    let url = API_BASE + 'rooms.php';
    const params = [];
    
    if (searchTerm) params.push(`search=${encodeURIComponent(searchTerm)}`);
    if (typeFilter) params.push(`room_type=${encodeURIComponent(typeFilter)}`);
    if (availabilityFilter) params.push(`is_available=${encodeURIComponent(availabilityFilter)}`);
    
    if (params.length > 0) {
        url += '?' + params.join('&');
    }
    
    fetch(url)
        .then(response => response.json())
        .then(rooms => displayRooms(rooms || []))
        .catch(err => showAlert('Search error: ' + err.message, 'error'));
}

function filterRooms() {
    searchRooms();
}

function showRoomModal(roomId = null) {
    currentEditId = roomId;
    document.getElementById('roomModalTitle').textContent = roomId ? 'Edit Room' : 'Add Room';
    document.getElementById('roomSaveBtn').textContent = roomId ? 'Update Room' : 'Save Room';
    
    const modal = document.getElementById('roomModal');
    modal.classList.remove('hidden');
    setTimeout(() => modal.classList.add('show'), 10);
    
    // Clear form
    document.getElementById('roomForm').reset();
    
    if (roomId) {
        loadRoomDetails(roomId);
    }
}

async function loadRoomDetails(id) {
    try {
        const response = await fetch(API_BASE + `rooms.php?id=${id}`);
        const room = await response.json();
        
        if (room) {
            document.getElementById('roomNumber').value = room.room_number || '';
            document.getElementById('roomName').value = room.room_name || '';
            document.getElementById('roomType').value = room.room_type || 'general';
            document.getElementById('roomFloor').value = room.floor || '';
            document.getElementById('roomCapacity').value = room.capacity || 1;
            document.getElementById('roomIsAvailable').value = room.is_available || 1;
            document.getElementById('roomNotes').value = room.notes || '';
        }
    } catch (err) {
        showAlert('Error loading room details: ' + err.message, 'error');
    }
}

async function saveRoom(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    try {
        const url = currentEditId 
            ? API_BASE + `rooms.php?id=${currentEditId}`
            : API_BASE + 'rooms.php';
        
        const response = await fetch(url, {
            method: currentEditId ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Failed to save room');
        }
        
        const result = await response.json();
        hideModal();
        loadRooms();
        showToast(
            currentEditId ? 'Room Updated' : 'Room Added',
            `${result.room_name} (${result.room_number}) has been ${currentEditId ? 'updated' : 'added to the system'}`,
            'success'
        );
    } catch (err) {
        showAlert('Error saving room: ' + err.message, 'error');
    }
}

function editRoom(id) {
    showRoomModal(id);
}

async function deleteRoom(id) {
    if (!confirm('Are you sure you want to delete this room?')) return;
    
    try {
        const response = await fetch(API_BASE + `rooms.php?id=${id}`, {
            method: 'DELETE'
        });
        
        if (!response.ok) throw new Error('Failed to delete room');
        
        loadRooms();
        showToast('Room Deleted', 'Room has been removed from the system', 'success');
    } catch (err) {
        showAlert('Error deleting room: ' + err.message, 'error');
    }
}

// Appointment Functions
async function loadAppointments() {
    try {
        const response = await fetch(API_BASE + 'appointments.php');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        const appointments = Array.isArray(data) ? data : [];
        displayAppointments(appointments);
    } catch (err) {
        console.error('Error loading appointments:', err);
        showAlert('Error loading appointments: ' + err.message, 'error');
        displayAppointments([]);
    }
}

function displayAppointments(appointments) {
    const tbody = document.querySelector('#appointmentsTable tbody');
    
    if (appointments.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="empty-state">
                    <div class="empty-state-text">No appointments found</div>
                    <div class="empty-state-subtext">Schedule your first appointment to get started</div>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = appointments.map(a => `
        <tr>
            <td>
                <div style="font-weight: 500;">${escapeHtml(a.patient_first_name)} ${escapeHtml(a.patient_last_name)}</div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">Patient ID: ${a.patient_id}</div>
            </td>
            <td>
                ${a.doctor_first_name ? `${escapeHtml(a.doctor_first_name)} ${escapeHtml(a.doctor_last_name)}` : 'Not assigned'}
                ${a.specialty ? `<br><small>${escapeHtml(a.specialty)}</small>` : ''}
            </td>
            <td>
                <div>${formatDateTime(a.start_time)}</div>
                ${a.end_time ? `<small>to ${formatDateTime(a.end_time)}</small>` : ''}
            </td>
            <td>
                ${a.room_number ? `<div style="font-weight: 500;"><i class="fas fa-hospital"></i> ${escapeHtml(a.room_number)}</div>` : ''}
                ${a.room_name ? `<div style="font-size: 0.75rem; color: var(--text-muted);">${escapeHtml(a.room_name)}</div>` : ''}
                ${!a.room_number && !a.room_name ? '<span style="color: var(--text-muted);">No room assigned</span>' : ''}
            </td>
            <td>
                <span class="status-badge status-${a.status}">${escapeHtml(a.status)}</span>
            </td>
            <td>${escapeHtml(a.reason || 'Not specified')}</td>
            <td class="action-buttons">
                ${hasPermission('appointments', 'edit') ? `
                <button class="btn btn-sm btn-secondary" onclick="editAppointment(${a.id})">
                    Edit
                </button>
                ` : ''}
                ${hasPermission('appointments', 'delete') ? `
                <button class="btn btn-sm btn-danger" onclick="deleteAppointment(${a.id})">
                    Cancel
                </button>
                ` : ''}
            </td>
        </tr>
    `).join('');
}

function filterAppointments() {
    const dateFrom = document.getElementById('appointmentDateFrom').value;
    const dateTo = document.getElementById('appointmentDateTo').value;
    const statusFilter = document.getElementById('appointmentStatusFilter').value;
    
    let url = API_BASE + 'appointments.php';
    const params = [];
    
    if (dateFrom) params.push(`date_from=${encodeURIComponent(dateFrom)}`);
    if (dateTo) params.push(`date_to=${encodeURIComponent(dateTo)}`);
    if (statusFilter) params.push(`status=${encodeURIComponent(statusFilter)}`);
    
    if (params.length > 0) {
        url += '?' + params.join('&');
    }
    
    fetch(url)
        .then(response => response.json())
        .then(appointments => displayAppointments(appointments || []))
        .catch(err => showAlert('Filter error: ' + err.message, 'error'));
}

function showAppointmentModal(appointmentId = null) {
    currentEditId = appointmentId;
    document.getElementById('appointmentModalTitle').textContent = appointmentId ? 'Edit Appointment' : 'Schedule Appointment';
    document.getElementById('appointmentSaveBtn').textContent = appointmentId ? 'Update Appointment' : 'Schedule Appointment';
    
    const modal = document.getElementById('appointmentModal');
    modal.classList.remove('hidden');
    setTimeout(() => modal.classList.add('show'), 10);
    
    // Load patients, doctors, and rooms into dropdowns
    loadPatientsForDropdown('appointmentPatient');
    loadDoctorsForDropdown('appointmentDoctor');
    loadRoomsForDropdown();
    
    if (appointmentId) {
        loadAppointmentDetails(appointmentId);
    }
}

function editAppointment(id) {
    showAppointmentModal(id);
}

async function deleteAppointment(id) {
    if (!confirm('Are you sure you want to cancel this appointment?')) return;
    
    try {
        const response = await fetch(API_BASE + `appointments.php?id=${id}`, {
            method: 'DELETE'
        });
        
        if (!response.ok) throw new Error('Failed to cancel appointment');
        
        loadAppointments();
        showToast('Appointment Cancelled', 'The appointment has been cancelled', 'success');
    } catch (err) {
        showAlert('Error cancelling appointment: ' + err.message, 'error');
    }
}

// Medical Records Functions
async function loadMedicalRecords() {
    try {
        const response = await fetch(API_BASE + 'medical_records.php');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        const records = Array.isArray(data) ? data : [];
        displayMedicalRecords(records);
        
        // Populate patient filter dropdown
        loadPatientsForFilter();
    } catch (err) {
        console.error('Error loading medical records:', err);
        showAlert('Error loading medical records: ' + err.message, 'error');
        displayMedicalRecords([]);
    }
}

async function loadPatientsForFilter() {
    try {
        const response = await fetch(API_BASE + 'patients.php');
        const patients = await response.json();
        const select = document.getElementById('recordPatientFilter');
        
        if (!select) return;
        
        // Keep the "All Patients" option
        const currentValue = select.value;
        select.innerHTML = '<option value="">All Patients</option>';
        
        if (Array.isArray(patients)) {
            patients.forEach(patient => {
                const option = document.createElement('option');
                option.value = patient.id;
                option.textContent = `${patient.first_name} ${patient.last_name}`;
                select.appendChild(option);
            });
        }
        
        // Restore previous selection if any
        if (currentValue) {
            select.value = currentValue;
        }
    } catch (err) {
        console.error('Error loading patients for filter:', err);
    }
}

function displayMedicalRecords(records) {
    const tbody = document.querySelector('#recordsTable tbody');
    if (!tbody) return;

    if (!Array.isArray(records) || records.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="empty-state">
                    <div class="empty-state-text">No medical records found</div>
                    <div class="empty-state-subtext">Add your first medical record to get started</div>
                </td>
            </tr>
        `;
        return;
    }

    const rows = records.map(r => `
        <tr>
            <td>
                <div style="font-weight:500;">${escapeHtml(r.patient_first_name)} ${escapeHtml(r.patient_last_name)}</div>
                <div style="font-size:0.75rem;color:var(--text-muted);">ID: ${r.patient_id}</div>
            </td>
            <td><span class="status-badge status-scheduled" style="text-transform:capitalize;">${escapeHtml((r.record_type||'').replace('_',' '))}</span></td>
            <td>${escapeHtml(r.title || '')}</td>
            <td>${formatDateTime(r.created_at)}</td>
            <td>${escapeHtml(r.created_by_name || 'Unknown')}</td>
            <td class="action-buttons">
                <button class="btn btn-sm btn-secondary" onclick="viewRecord(${r.id})">View</button>
                ${hasPermission('medical_records', 'edit') ? `<button class=\"btn btn-sm btn-primary\" onclick=\"showRecordModal(${r.id})\">Edit</button>` : ''}
                ${hasPermission('medical_records', 'delete') ? `<button class=\"btn btn-sm btn-danger\" onclick=\"deleteRecord(${r.id})\">Delete</button>` : ''}
            </td>
        </tr>
    `).join('');

    tbody.innerHTML = rows;
}

function filterRecords() {
    const patientFilter = document.getElementById('recordPatientFilter').value;
    const typeFilter = document.getElementById('recordTypeFilter').value;
    
    let url = API_BASE + 'medical_records.php';
    const params = [];
    
    if (patientFilter) params.push(`patient_id=${encodeURIComponent(patientFilter)}`);
    if (typeFilter) params.push(`record_type=${encodeURIComponent(typeFilter)}`);
    
    if (params.length > 0) {
        url += '?' + params.join('&');
    }
    
    fetch(url)
        .then(response => response.json())
        .then(records => displayMedicalRecords(records || []))
        .catch(err => showAlert('Filter error: ' + err.message, 'error'));
}

function showRecordModal(recordId = null) {
    currentEditId = recordId;
    document.getElementById('recordModalTitle').textContent = recordId ? 'Edit Medical Record' : 'Add Medical Record';
    document.getElementById('recordSaveBtn').textContent = recordId ? 'Update Record' : 'Save Record';
    
    const modal = document.getElementById('recordModal');
    modal.classList.remove('hidden');
    setTimeout(() => modal.classList.add('show'), 10);
    
    // Load patients and appointments into dropdowns
    loadPatientsForDropdown('recordPatient');
    loadAppointmentsForDropdown('recordAppointment');
    
    // Clear form
    document.getElementById('recordForm').reset();
    
    if (recordId) {
        loadRecordDetails(recordId);
    }
}

async function loadRecordDetails(id) {
    try {
        const response = await fetch(API_BASE + `medical_records.php?id=${id}`);
        const record = await response.json();
        
        if (record) {
            document.getElementById('recordPatient').value = record.patient_id || '';
            document.getElementById('recordAppointment').value = record.appointment_id || '';
            document.getElementById('recordType').value = record.record_type || '';
            document.getElementById('recordTitle').value = record.title || '';
            document.getElementById('recordContent').value = record.content || '';
        }
    } catch (err) {
        showAlert('Error loading record details: ' + err.message, 'error');
    }
}

async function saveRecord(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    try {
        const url = currentEditId 
            ? API_BASE + `medical_records.php?id=${currentEditId}`
            : API_BASE + 'medical_records.php';
        
        const response = await fetch(url, {
            method: currentEditId ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Failed to save record');
        }
        
        const result = await response.json();
        hideModal();
        loadMedicalRecords();
        showToast(
            currentEditId ? 'Record Updated' : 'Record Added',
            `Medical record for ${result.patient_first_name} ${result.patient_last_name} has been ${currentEditId ? 'updated' : 'saved'}`,
            'success'
        );
    } catch (err) {
        showAlert('Error saving record: ' + err.message, 'error');
    }
}

async function viewRecord(id) {
    try {
        const response = await fetch(API_BASE + `medical_records.php?id=${id}`);
        const record = await response.json();
        
        if (!record) {
            showAlert('Record not found', 'error');
            return;
        }
        
        // Show the view modal
        const modal = document.getElementById('viewRecordModal');
        modal.classList.remove('hidden');
        setTimeout(() => modal.classList.add('show'), 10);
        
        displayRecordDetails(record);
    } catch (err) {
        showAlert('Error loading record: ' + err.message, 'error');
    }
}

function displayRecordDetails(record) {
    const content = document.getElementById('viewRecordContent');
    
    const recordTypeIcons = {
        'diagnosis': '<i class="fas fa-microscope"></i>',
        'treatment': '<i class="fas fa-pills"></i>',
        'prescription': '<i class="fas fa-prescription"></i>',
        'lab_result': '<i class="fas fa-flask"></i>',
        'imaging': '<i class="fas fa-x-ray"></i>'
    };
    
    const recordTypeColors = {
        'diagnosis': '#3b82f6',
        'treatment': '#10b981',
        'prescription': '#f59e0b',
        'lab_result': '#8b5cf6',
        'imaging': '#06b6d4'
    };
    
    const icon = recordTypeIcons[record.record_type] || '📄';
    const color = recordTypeColors[record.record_type] || '#6b7280';
    
    content.innerHTML = `
        <!-- Record Header -->
        <div style="background: linear-gradient(135deg, ${color}, ${color}dd); color: white; padding: 2rem; margin: -2rem -2rem 2rem -2rem; border-radius: var(--radius-lg) var(--radius-lg) 0 0;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="font-size: 3rem;">${icon}</div>
                <div style="flex: 1;">
                    <h2 style="margin: 0; font-size: 1.5rem; font-weight: 700;">${escapeHtml(record.title)}</h2>
                    <div style="margin-top: 0.5rem; font-size: 0.875rem; opacity: 0.9;">
                        <span style="text-transform: capitalize;">${escapeHtml(record.record_type?.replace('_', ' '))}</span>
                        <span style="margin: 0 0.5rem;">•</span>
                        ${formatDateTime(record.created_at)}
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Patient Information -->
        <div style="margin-bottom: 1.5rem;">
            <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--border-light);">Patient Information</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Patient Name</div>
                    <div style="font-weight: 500;">${escapeHtml(record.patient_first_name)} ${escapeHtml(record.patient_last_name)}</div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Patient ID</div>
                    <div style="font-weight: 500;">#${record.patient_id}</div>
                </div>
                ${record.appointment_id ? `
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Appointment ID</div>
                    <div style="font-weight: 500;">#${record.appointment_id}</div>
                </div>
                ` : ''}
            </div>
        </div>
        
        <!-- Record Content -->
        ${record.content ? `
        <div style="margin-bottom: 1.5rem;">
            <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--border-light);">Content & Notes</h3>
            <div style="background: var(--bg-secondary); padding: 1.5rem; border-radius: var(--radius); white-space: pre-wrap; line-height: 1.6;">${escapeHtml(record.content)}</div>
        </div>
        ` : ''}
        
        <!-- Metadata -->
        <div style="margin-bottom: 1.5rem;">
            <h3 style="font-size: 1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--border-light);">Record Information</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Created By</div>
                    <div style="font-weight: 500;">${escapeHtml(record.created_by_name || 'Unknown')}</div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Created At</div>
                    <div style="font-weight: 500;">${formatDateTime(record.created_at)}</div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Record ID</div>
                    <div style="font-weight: 500;">#${record.id}</div>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem; padding-top: 1.5rem; border-top: 2px solid var(--border-light);">
            ${hasPermission('medical_records', 'edit') ? `
            <button class="btn btn-primary" onclick="closeViewRecordAndEdit(${record.id})">
                Edit Record
            </button>
            ` : ''}
            <button class="btn btn-secondary" onclick="closeViewRecord()">
                Close
            </button>
        </div>
    `;
}

function closeViewRecord() {
    const modal = document.getElementById('viewRecordModal');
    modal.classList.remove('show');
    setTimeout(() => modal.classList.add('hidden'), 300);
}

function closeViewRecordAndEdit(id) {
    closeViewRecord();
    setTimeout(() => showRecordModal(id), 400);
}

async function deleteRecord(id) {
    if (!confirm('Are you sure you want to delete this medical record?')) return;
    
    try {
        const response = await fetch(API_BASE + `medical_records.php?id=${id}`, {
            method: 'DELETE'
        });
        
        if (!response.ok) throw new Error('Failed to delete record');
        
        loadMedicalRecords();
        showToast('Record Deleted', 'Medical record has been removed', 'success');
    } catch (err) {
        showAlert('Error deleting record: ' + err.message, 'error');
    }
}

// Billing Functions
async function loadBilling() {
    try {
        const response = await fetch(API_BASE + 'billing.php');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        const billing = Array.isArray(data) ? data : [];
        displayBilling(billing);
    } catch (err) {
        console.error('Error loading billing:', err);
        showAlert('Error loading billing: ' + err.message, 'error');
        displayBilling([]);
    }
}

function displayBilling(billing) {
    const tbody = document.querySelector('#billingTable tbody');
    
    if (billing.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="empty-state">
                    <div class="empty-state-text">No billing records found</div>
                    <div class="empty-state-subtext">Add your first bill to get started</div>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = billing.map(b => {
        const isPending = b.status === 'pending';
        const hasMpesaTransaction = b.transaction_status && b.transaction_status !== 'failed' && b.transaction_status !== 'cancelled';
        
        return `
        <tr>
            <td>
                <div style="font-weight: 500;">${escapeHtml(b.patient_first_name)} ${escapeHtml(b.patient_last_name)}</div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">Patient ID: ${b.patient_id}</div>
            </td>
            <td>${formatCurrency(b.amount)}</td>
            <td>
                <span class="status-badge status-${b.status}">${escapeHtml(b.status)}</span>
                ${hasMpesaTransaction ? `<br><small class="mpesa-badge">${escapeHtml(b.transaction_status)}</small>` : ''}
                ${b.mpesa_receipt_number ? `<br><small style="font-size: 0.7rem; color: var(--success);">Receipt: ${escapeHtml(b.mpesa_receipt_number)}</small>` : ''}
            </td>
            <td>${formatDate(b.due_date)}</td>
            <td>${escapeHtml(b.payment_method || 'Not specified')}</td>
            <td class="action-buttons">
                ${isPending && hasPermission('billing', 'edit') ? `
                <button class="btn btn-sm btn-success" onclick="showMpesaPayment(${b.id}, '${escapeHtml(b.patient_first_name)} ${escapeHtml(b.patient_last_name)}', ${b.amount})">
                    <i class="fas fa-mobile-alt"></i> Pay with M-Pesa
                </button>
                ` : ''}
                ${hasPermission('billing', 'edit') ? `
                <button class="btn btn-sm btn-secondary" onclick="editBill(${b.id})">
                    Edit
                </button>
                ` : ''}
                ${hasPermission('billing', 'delete') ? `
                <button class="btn btn-sm btn-danger" onclick="deleteBill(${b.id})">
                    Delete
                </button>
                ` : ''}
            </td>
        </tr>
    `;
    }).join('');
}

function filterBilling() {
    const patientFilter = document.getElementById('billingPatientFilter').value;
    const statusFilter = document.getElementById('billingStatusFilter').value;
    
    let url = API_BASE + 'billing.php';
    const params = [];
    
    if (patientFilter) params.push(`patient_id=${encodeURIComponent(patientFilter)}`);
    if (statusFilter) params.push(`status=${encodeURIComponent(statusFilter)}`);
    
    if (params.length > 0) {
        url += '?' + params.join('&');
    }
    
    fetch(url)
        .then(response => response.json())
        .then(billing => displayBilling(billing || []))
        .catch(err => showAlert('Filter error: ' + err.message, 'error'));
}

function showBillingModal(billId = null) {
    currentEditId = billId;
    document.getElementById('billingModalTitle').textContent = billId ? 'Edit Bill' : 'Add Bill';
    document.getElementById('billingSaveBtn').textContent = billId ? 'Update Bill' : 'Add Bill';
    
    const modal = document.getElementById('billingModal');
    modal.classList.remove('hidden');
    setTimeout(() => modal.classList.add('show'), 10);
    
    // Load patients into dropdown
    loadPatientsForDropdown('billingPatient');
    
    if (billId) {
        loadBillingDetails(billId);
    }
}

function editBill(id) {
    showBillingModal(id);
}

async function deleteBill(id) {
    if (!confirm('Are you sure you want to delete this billing record?')) return;
    
    try {
        const response = await fetch(API_BASE + `billing.php?id=${id}`, {
            method: 'DELETE'
        });
        
        if (!response.ok) throw new Error('Failed to delete bill');
        
        loadBilling();
        showToast('Bill Deleted', 'Billing record has been removed', 'success');
    } catch (err) {
        showAlert('Error deleting bill: ' + err.message, 'error');
    }
}

// Reports Functions
async function loadReport() {
    console.log('🔷 loadReport() called');
    const reportType = document.getElementById('reportType')?.value || 'revenue';
    const dateFrom = document.getElementById('reportDateFrom')?.value || '';
    const dateTo = document.getElementById('reportDateTo')?.value || '';

    // Show immediate loading state in the UI
    const reportTitle = document.getElementById('reportTitle');
    const reportContent = document.getElementById('reportContent');
    if (reportTitle) reportTitle.textContent = 'Loading...';
    if (reportContent) {
        reportContent.innerHTML = '<div style="padding:1rem;color:var(--text-muted);">Loading report...</div>';
    }
    
    console.log('🔷 Report type:', reportType, 'Date from:', dateFrom, 'Date to:', dateTo);
    
    try {
        let url = API_BASE + `reports.php?type=${reportType}`;
        const params = [];
        
        if (dateFrom) params.push(`date_from=${encodeURIComponent(dateFrom)}`);
        if (dateTo) params.push(`date_to=${encodeURIComponent(dateTo)}`);
        
        if (params.length > 0) {
            url += '&' + params.join('&');
        }
        
        console.log('🔷 Fetching URL:', url);
        const data = await apiFetch(url);
        console.log('🔷 Parsing JSON...');
        console.log('🔷 Data parsed:', data);
        console.log('🔷 Calling displayReport...');
        displayReport(reportType, data);
        console.log('🔷 displayReport completed');
    } catch (err) {
        console.error('Error loading report:', err);
        console.error('Error stack:', err.stack);
        showAlert('Error loading report: ' + err.message, 'error');
    }
}

function displayReport(type, data) {
    const reportTitle = document.getElementById('reportTitle');
    const reportContent = document.getElementById('reportContent');
    
    const titles = {
        'dashboard': 'Dashboard Overview',
        'patients': 'Patient Demographics',
        'appointments': 'Appointment Analytics',
        'revenue': 'Revenue Analysis'
    };
    
    reportTitle.textContent = titles[type] || 'Report';
    
    console.log('Report type:', type, 'Data:', data);
    
    let html = '';
    
    try {
        switch(type) {
            case 'dashboard':
                html = renderDashboardReport(data);
                break;
            case 'patients':
                html = renderPatientDemographicsReport(data);
                break;
            case 'appointments':
                html = renderAppointmentAnalyticsReport(data);
                break;
            case 'revenue':
                html = renderRevenueAnalysisReport(data);
                break;
            default:
                html = `<div class="report-section"><p>No report available</p></div>`;
        }
    } catch (error) {
        console.error('Report render error:', error);
        html = `
            <div class="report-section">
                <h3>Error Rendering Report</h3>
                <p style="color: var(--danger);">${error.message}</p>
                <h3>Raw Data (Debug)</h3>
                <pre style="background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius); overflow-x: auto; max-height: 400px;">${JSON.stringify(data, null, 2)}</pre>
            </div>
        `;
    }
    
reportContent.innerHTML = html;
}

// Ensure global access for inline handlers
window.loadReport = loadReport;
window.displayReport = displayReport;

function renderDashboardReport(data) {
    return `
        <div class="report-section">
            <h3>System Overview</h3>
            <div class="report-stats-grid">
                <div class="report-stat-card">
                    <div class="report-stat-value">${data.total_patients || 0}</div>
                    <div class="report-stat-label">Total Patients</div>
                </div>
                <div class="report-stat-card">
                    <div class="report-stat-value">${data.new_patients_month || 0}</div>
                    <div class="report-stat-label">New This Month</div>
                </div>
                <div class="report-stat-card">
                    <div class="report-stat-value">${data.total_appointments || 0}</div>
                    <div class="report-stat-label">Appointments</div>
                </div>
                <div class="report-stat-card">
                    <div class="report-stat-value">${data.pending_appointments || 0}</div>
                    <div class="report-stat-label">Pending</div>
                </div>
                <div class="report-stat-card">
                    <div class="report-stat-value">${data.active_doctors || 0}</div>
                    <div class="report-stat-label">Doctors</div>
                </div>
                <div class="report-stat-card">
                    <div class="report-stat-value">$${(data.total_revenue || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}</div>
                    <div class="report-stat-label">Revenue</div>
                </div>
            </div>
        </div>
    `;
}

function renderPatientDemographicsReport(data) {
    const genderData = data.gender_distribution || [];
    const ageGroupData = data.age_groups || [];
    const monthlyData = data.monthly_registrations || [];
    
    const totalGender = genderData.reduce((sum, item) => sum + (item.count || 0), 0);
    
    const genderItems = genderData.map(item => `
        <div class="breakdown-item">
            <div class="breakdown-item-label">${item.gender || 'Unknown'}</div>
            <div class="breakdown-item-value">${item.count || 0}</div>
            <div class="breakdown-item-sublabel">${totalGender > 0 ? ((item.count/totalGender)*100).toFixed(1) : 0}% of total</div>
        </div>
    `).join('');
    
    const ageItems = ageGroupData.map(item => `
        <div class="breakdown-item">
            <div class="breakdown-item-label">${item.age_group || 'Unknown'}</div>
            <div class="breakdown-item-value">${item.count || 0}</div>
        </div>
    `).join('');
    
    return `
        <div class="report-section">
            <h3>Total Patients: ${totalGender}</h3>
        </div>
        <div class="report-section">
            <h3>Gender Distribution</h3>
            <div class="report-breakdown">
                ${genderItems || '<div style="color: var(--text-muted);">No data available</div>'}
            </div>
        </div>
        <div class="report-section">
            <h3>Age Groups</h3>
            <div class="report-breakdown">
                ${ageItems || '<div style="color: var(--text-muted);">No data available</div>'}
            </div>
        </div>
    `;
}

function renderAppointmentAnalyticsReport(data) {
    const statusData = data.status_distribution || [];
    const doctorData = data.doctor_workload || [];
    
    const totalAppts = statusData.reduce((sum, item) => sum + (item.count || 0), 0);
    
    const statusItems = statusData.map(item => `
        <tr>
            <td>
                <span class="report-badge ${item.status?.toLowerCase()}">${item.status}</span>
            </td>
            <td style="font-weight: 600;">${item.count || 0}</td>
            <td>${totalAppts > 0 ? ((item.count/totalAppts)*100).toFixed(1) : 0}%</td>
        </tr>
    `).join('');
    
    const doctorItems = doctorData.map(doc => `
        <tr>
            <td><strong>${doc.first_name} ${doc.last_name}</strong></td>
            <td>${doc.specialty || '-'}</td>
            <td style="text-align: center; font-weight: 600;">${doc.appointment_count || 0}</td>
        </tr>
    `).join('');
    
    return `
        <div class="report-section">
            <h3>Appointment Summary</h3>
            <div class="report-stats-grid">
                <div class="report-stat-card">
                    <div class="report-stat-value">${totalAppts}</div>
                    <div class="report-stat-label">Total Appointments</div>
                </div>
            </div>
        </div>
        <div class="report-section">
            <h3>Status Breakdown</h3>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Count</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    ${statusItems || '<tr><td colspan="3" style="text-align: center; color: var(--text-muted);">No data available</td></tr>'}
                </tbody>
            </table>
        </div>
        <div class="report-section">
            <h3>Doctor Workload</h3>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Doctor</th>
                        <th>Specialty</th>
                        <th style="text-align: center;">Appointments</th>
                    </tr>
                </thead>
                <tbody>
                    ${doctorItems || '<tr><td colspan="3" style="text-align: center; color: var(--text-muted);">No data available</td></tr>'}
                </tbody>
            </table>
        </div>
    `;
}

// Store chart instances to destroy them before recreating
let revenueCharts = [];

function renderRevenueAnalysisReport(data) {
    const revenueByStatus = data.revenue_by_status || [];
    const paymentMethods = data.payment_methods || [];
    const monthlyRevenue = data.monthly_revenue || [];
    
    const totalRevenue = revenueByStatus.reduce((sum, item) => sum + parseFloat(item.total_amount || 0), 0);
    const paidAmount = revenueByStatus.find(item => item.status === 'paid')?.total_amount || 0;
    const pendingAmount = revenueByStatus.find(item => item.status === 'pending')?.total_amount || 0;
    
    const html = `
        <div class="report-section">
            <h3>Revenue Overview</h3>
            <div class="report-stats-grid">
                <div class="report-stat-card">
                    <div class="report-stat-value">$${totalRevenue.toLocaleString('en-US', { minimumFractionDigits: 2 })}</div>
                    <div class="report-stat-label">Total Revenue</div>
                </div>
                <div class="report-stat-card">
                    <div class="report-stat-value">$${parseFloat(paidAmount).toLocaleString('en-US', { minimumFractionDigits: 2 })}</div>
                    <div class="report-stat-label">Amount Paid</div>
                </div>
                <div class="report-stat-card">
                    <div class="report-stat-value">$${parseFloat(pendingAmount).toLocaleString('en-US', { minimumFractionDigits: 2 })}</div>
                    <div class="report-stat-label">Pending</div>
                </div>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <div class="report-section" style="background: white; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3>Revenue by Status</h3>
                <canvas id="revenueStatusChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
        
        ${monthlyRevenue && monthlyRevenue.length > 0 ? `
            <div class="report-section" style="background: white; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 2rem;">
                <h3>Monthly Revenue Trend</h3>
                <canvas id="monthlyRevenueChart" style="max-height: 350px;"></canvas>
            </div>
        ` : ''}
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="report-section">
                <h3>Revenue by Status - Details</h3>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th style="text-align: center;">Count</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${revenueByStatus.map(item => `
                            <tr>
                                <td><span class="report-badge ${item.status?.toLowerCase()}" style="text-transform: capitalize;">${item.status}</span></td>
                                <td style="text-align: center; font-weight: 600;">${item.count || 0}</td>
                                <td style="font-weight: 600;">$${parseFloat(item.total_amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                            </tr>
                        `).join('') || '<tr><td colspan="3" style="text-align: center; color: var(--text-muted);">No data available</td></tr>'}
                    </tbody>
                </table>
            </div>
            
            <div class="report-section">
                <h3>Payment Methods - Details</h3>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th style="text-align: center;">Count</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${paymentMethods.map(method => `
                            <tr>
                                <td>${method.payment_method || 'Not specified'}</td>
                                <td style="text-align: center;">${method.count || 0}</td>
                                <td style="font-weight: 600;">$${parseFloat(method.total_amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                            </tr>
                        `).join('') || '<tr><td colspan="3" style="text-align: center; color: var(--text-muted);">No data available</td></tr>'}
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    // Destroy existing charts
    revenueCharts.forEach(chart => {
        if (chart) chart.destroy();
    });
    revenueCharts = [];
    
    // Render charts after DOM is updated
    setTimeout(() => {
        if (typeof Chart !== 'undefined') {
            renderRevenueCharts(revenueByStatus, monthlyRevenue);
        } else {
            console.error('Chart.js library not loaded. Charts cannot be rendered.');
            // Show fallback message in chart containers
            const chartContainers = document.querySelectorAll('#revenueStatusChart, #monthlyRevenueChart');
            chartContainers.forEach(container => {
                if (container && container.parentElement) {
                    const parent = container.parentElement;
                    if (!parent.querySelector('.chart-error')) {
                        const errorMsg = document.createElement('div');
                        errorMsg.className = 'chart-error';
                        errorMsg.style.cssText = 'color: var(--text-muted); padding: 2rem; text-align: center;';
                        errorMsg.textContent = 'Chart library loading... Please refresh the page.';
                        container.style.display = 'none';
                        parent.appendChild(errorMsg);
                    }
                }
            });
        }
    }, 100);
    
    return html;
}

function renderRevenueCharts(revenueByStatus, monthlyRevenue) {
    // Check if Chart.js is loaded
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded');
        return;
    }
    
    // Revenue by Status - Pie Chart
    const statusCtx = document.getElementById('revenueStatusChart');
    if (statusCtx && revenueByStatus.length > 0) {
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: revenueByStatus.map(item => item.status ? item.status.charAt(0).toUpperCase() + item.status.slice(1) : 'Unknown'),
                datasets: [{
                    label: 'Revenue',
                    data: revenueByStatus.map(item => parseFloat(item.total_amount || 0)),
                    backgroundColor: [
                        '#10b981', // green for paid
                        '#f59e0b', // yellow for pending
                        '#ef4444', // red for cancelled
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: { size: 12 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                return label + ': $' + value.toLocaleString('en-US', { minimumFractionDigits: 2 });
                            }
                        }
                    }
                }
            }
        });
        revenueCharts.push(statusChart);
    }
    
    // Monthly Revenue - Line Chart
    const monthlyCtx = document.getElementById('monthlyRevenueChart');
    if (monthlyCtx && monthlyRevenue && monthlyRevenue.length > 0) {
        const monthlyChart = new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: monthlyRevenue.map(m => m.month || ''),
                datasets: [{
                    label: 'Monthly Revenue',
                    data: monthlyRevenue.map(m => parseFloat(m.total_amount || 0)),
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3,
                    pointRadius: 5,
                    pointBackgroundColor: '#8b5cf6',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '$' + context.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2 });
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString('en-US');
                            }
                        }
                    }
                }
            }
        });
        revenueCharts.push(monthlyChart);
    }
}

console.log('All report render functions defined');

function exportData() {
    showAlert('Export functionality coming soon!');
}

// Helper Functions for Dropdowns
async function loadPatientsForDropdown(selectId) {
    try {
        const response = await fetch(API_BASE + 'patients.php');
        const patients = await response.json();
        const select = document.getElementById(selectId);
        
        // Clear existing options except the first one
        select.innerHTML = '<option value="">Select Patient</option>';
        
        if (Array.isArray(patients)) {
            patients.forEach(patient => {
                const option = document.createElement('option');
                option.value = patient.id;
                option.textContent = `${patient.first_name} ${patient.last_name}`;
                select.appendChild(option);
            });
        }
    } catch (err) {
        console.error('Error loading patients for dropdown:', err);
    }
}

async function loadDoctorsForDropdown(selectId) {
    try {
        const response = await fetch(API_BASE + 'doctors.php');
        const doctors = await response.json();
        const select = document.getElementById(selectId);
        
        // Clear existing options except the first one
        select.innerHTML = '<option value="">Select Doctor</option>';
        
        if (Array.isArray(doctors)) {
            doctors.forEach(doctor => {
                const option = document.createElement('option');
                option.value = doctor.id;
                option.textContent = `Dr. ${doctor.first_name} ${doctor.last_name} - ${doctor.specialty || 'General'}`;
                select.appendChild(option);
            });
        }
    } catch (err) {
        console.error('Error loading doctors for dropdown:', err);
    }
}

async function loadAppointmentsForDropdown(selectId) {
    try {
        const response = await fetch(API_BASE + 'appointments.php');
        const appointments = await response.json();
        const select = document.getElementById(selectId);
        
        // Clear existing options except the first one
        select.innerHTML = '<option value="">No related appointment</option>';
        
        if (Array.isArray(appointments)) {
            // Only show upcoming or recent appointments
            const relevantAppointments = appointments.filter(a => a.status !== 'cancelled');
            relevantAppointments.forEach(appointment => {
                const option = document.createElement('option');
                option.value = appointment.id;
                const date = formatDate(appointment.start_time);
                option.textContent = `${appointment.patient_first_name} ${appointment.patient_last_name} - ${date}`;
                select.appendChild(option);
            });
        }
    } catch (err) {
        console.error('Error loading appointments for dropdown:', err);
    }
}

// Appointment Save Function
async function saveAppointment(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    // Convert datetime-local to proper format
    if (data.start_time) {
        data.start_time = data.start_time.replace('T', ' ') + ':00';
    }
    if (data.end_time) {
        data.end_time = data.end_time.replace('T', ' ') + ':00';
    }
    
    try {
        const url = currentEditId 
            ? API_BASE + `appointments.php?id=${currentEditId}`
            : API_BASE + 'appointments.php';
        
        const response = await fetch(url, {
            method: currentEditId ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        // Handle conflict errors (409)
        if (response.status === 409) {
            const error = await response.json();
            showToast('Booking Conflict', error.error || 'Time slot not available', 'error');
            return;
        }
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Failed to save appointment');
        }
        
        const result = await response.json();
        hideModal();
        loadAppointments();
        showToast(
            currentEditId ? 'Appointment Updated' : 'Appointment Scheduled',
            currentEditId ? `Appointment for ${result.patient_first_name} ${result.patient_last_name} has been updated` : `Appointment scheduled for ${result.patient_first_name} ${result.patient_last_name}`,
            'success'
        );
    } catch (err) {
        showAlert('Error saving appointment: ' + err.message, 'error');
    }
}

// Billing Save Function
async function saveBilling(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    // Convert datetime-local to proper format
    if (data.payment_date) {
        data.payment_date = data.payment_date.replace('T', ' ') + ':00';
    }
    
    try {
        const url = currentEditId 
            ? API_BASE + `billing.php?id=${currentEditId}`
            : API_BASE + 'billing.php';
        
        const response = await fetch(url, {
            method: currentEditId ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        if (!response.ok) throw new Error('Failed to save billing record');
        
        const result = await response.json();
        hideModal();
        loadBilling();
        showToast(
            currentEditId ? 'Bill Updated' : 'Bill Added',
            currentEditId ? `Bill for ${result.patient_first_name} ${result.patient_last_name} has been updated` : `Bill added for ${result.patient_first_name} ${result.patient_last_name}`,
            'success'
        );
    } catch (err) {
        showAlert('Error saving bill: ' + err.message, 'error');
    }
}

async function loadRoomsForDropdown() {
    try {
        const response = await fetch(API_BASE + 'rooms.php?is_available=1');
        const rooms = await response.json();
        const select = document.getElementById('appointmentRoom');
        
        if (!select) return;
        
        // Clear existing options except the first one
        select.innerHTML = '<option value="">Select Room (Optional)</option>';
        
        if (Array.isArray(rooms)) {
            rooms.forEach(room => {
                const option = document.createElement('option');
                option.value = room.id;
                option.textContent = `${room.room_number} - ${room.room_name} (${room.room_type})`;
                select.appendChild(option);
            });
        }
    } catch (err) {
        console.error('Error loading rooms for dropdown:', err);
    }
}

// Load appointment details for editing
async function loadAppointmentDetails(id) {
    try {
        const response = await fetch(API_BASE + `appointments.php?id=${id}`);
        const appointment = await response.json();
        
        if (appointment) {
            document.getElementById('appointmentPatient').value = appointment.patient_id || '';
            document.getElementById('appointmentDoctor').value = appointment.doctor_id || '';
            
            // Convert datetime format for input fields
            if (appointment.start_time) {
                const startTime = new Date(appointment.start_time);
                document.getElementById('appointmentStartTime').value = startTime.toISOString().slice(0, 16);
            }
            
            if (appointment.end_time) {
                const endTime = new Date(appointment.end_time);
                document.getElementById('appointmentEndTime').value = endTime.toISOString().slice(0, 16);
            }
            
            document.getElementById('appointmentStatus').value = appointment.status || 'scheduled';
            document.getElementById('appointmentReason').value = appointment.reason || '';
        }
    } catch (err) {
        showAlert('Error loading appointment details: ' + err.message, 'error');
    }
}

// Load billing details for editing
async function loadBillingDetails(id) {
    try {
        const response = await fetch(API_BASE + `billing.php?id=${id}`);
        const billing = await response.json();
        
        if (billing) {
            document.getElementById('billingPatient').value = billing.patient_id || '';
            document.getElementById('billingAmount').value = billing.amount || '';
            document.getElementById('billingStatus').value = billing.status || 'pending';
            document.getElementById('billingDueDate').value = billing.due_date || '';
            document.getElementById('billingPaymentMethod').value = billing.payment_method || '';
            
            if (billing.payment_date) {
                const paymentDate = new Date(billing.payment_date);
                document.getElementById('billingPaymentDate').value = paymentDate.toISOString().slice(0, 16);
            }
            
            document.getElementById('billingNotes').value = billing.notes || '';
        }
    } catch (err) {
        showAlert('Error loading billing details: ' + err.message, 'error');
    }
}

// Availability checking functions
let selectedAvailability = {
    doctor: null,
    room: null,
    timeSlot: null
};

function showAvailabilityModal() {
    const modal = document.getElementById('availabilityModal');
    if (!modal) {
        console.error('Availability modal not found');
        showAlert('Availability modal not found', 'error');
        return;
    }
    
    // Don't close the appointment modal, just hide it temporarily
    const appointmentModal = document.getElementById('appointmentModal');
    if (appointmentModal) {
        appointmentModal.style.display = 'none';
    }
    
    modal.classList.remove('hidden');
    setTimeout(() => modal.classList.add('show'), 10);
    
    // Set default date to today
    const today = new Date().toISOString().split('T')[0];
    const dateInput = document.getElementById('availabilityDate');
    if (dateInput) {
        dateInput.value = today;
    }
    
    // Reset selections
    selectedAvailability = { doctor: null, room: null, timeSlot: null };
    
    // Load availability
    checkAvailability();
}

async function checkAvailability() {
    const dateInput = document.getElementById('availabilityDate');
    if (!dateInput) {
        console.error('Availability date input not found');
        return;
    }
    
    const date = dateInput.value;
    
    if (!date) {
        showAlert('Please select a date', 'error');
        return;
    }
    
    const resultsDiv = document.getElementById('availabilityResults');
    if (!resultsDiv) {
        console.error('Availability results div not found');
        return;
    }
    
    resultsDiv.innerHTML = '<div style="text-align: center; padding: 2rem;">Loading availability...</div>';
    
    try {
        const response = await fetch(`${API_BASE}appointments.php?action=check_availability&date=${date}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        // Check if response contains error
        if (data.error) {
            throw new Error(data.error);
        }
        
        displayAvailability(data);
    } catch (err) {
        console.error('Error checking availability:', err);
        showAlert('Error checking availability: ' + err.message, 'error');
        resultsDiv.innerHTML = '<div class="empty-availability">Error loading availability: ' + escapeHtml(err.message) + '<br>Please check the console for more details.</div>';
    }
}

function displayAvailability(data) {
    const resultsDiv = document.getElementById('availabilityResults');
    
    let html = '';
    
    // Display available doctors
    html += '<div class="availability-section">';
    html += '<h3><i class="fas fa-user-md"></i> Available Doctors</h3>';
    
    if (data.available_doctors && data.available_doctors.length > 0) {
        html += '<div class="availability-grid">';
        data.available_doctors.forEach(doctor => {
            html += `
                <div class="availability-item" onclick="selectDoctor(${doctor.id}, '${escapeHtml(doctor.first_name)} ${escapeHtml(doctor.last_name)}')">
                    <div class="availability-item-title">Dr. ${escapeHtml(doctor.first_name)} ${escapeHtml(doctor.last_name)}</div>
                    <div class="availability-item-subtitle">${escapeHtml(doctor.specialty || 'General')}</div>
                </div>
            `;
        });
        html += '</div>';
    } else {
        html += '<div class="empty-availability">No doctors available for the selected date</div>';
    }
    html += '</div>';
    
    // Display available rooms
    html += '<div class="availability-section">';
    html += '<h3><i class="fas fa-hospital"></i> Available Rooms</h3>';
    
    if (data.available_rooms && data.available_rooms.length > 0) {
        html += '<div class="availability-grid">';
        data.available_rooms.forEach(room => {
            html += `
                <div class="availability-item" onclick="selectRoom(${room.id}, '${escapeHtml(room.room_name)}')">
                    <div class="availability-item-title">${escapeHtml(room.room_number)}</div>
                    <div class="availability-item-subtitle">${escapeHtml(room.room_name)}</div>
                    ${room.floor ? `<div class="availability-item-subtitle">Floor ${escapeHtml(room.floor)}</div>` : ''}
                </div>
            `;
        });
        html += '</div>';
    } else {
        html += '<div class="empty-availability">No rooms available for the selected date</div>';
    }
    html += '</div>';
    
    // Display time slots
    html += '<div class="availability-section">';
    html += '<h3><i class="fas fa-clock"></i> Available Time Slots</h3>';
    
    if (data.time_slots && data.time_slots.length > 0) {
        html += '<div class="time-slot-grid">';
        data.time_slots.forEach(slot => {
            html += `
                <div class="time-slot" onclick="selectTimeSlot('${slot.start}', '${slot.end}', '${slot.label}')">
                    ${escapeHtml(slot.label)}
                </div>
            `;
        });
        html += '</div>';
    } else {
        html += '<div class="empty-availability">No time slots available</div>';
    }
    html += '</div>';
    
    resultsDiv.innerHTML = html;
}

function selectDoctor(doctorId, doctorName) {
    selectedAvailability.doctor = { id: doctorId, name: doctorName };
    
    // Highlight selected
    document.querySelectorAll('.availability-item').forEach(item => {
        if (item.textContent.includes(doctorName)) {
            item.classList.add('selected');
        }
    });
    
    showToast('Doctor Selected', doctorName, 'success');
}

function selectRoom(roomId, roomName) {
    selectedAvailability.room = { id: roomId, name: roomName };
    
    // Highlight selected
    document.querySelectorAll('.availability-item').forEach(item => {
        if (item.textContent.includes(roomName)) {
            item.classList.add('selected');
        }
    });
    
    showToast('Room Selected', roomName, 'success');
}

function selectTimeSlot(startTime, endTime, label) {
    selectedAvailability.timeSlot = { start: startTime, end: endTime, label: label };
    
    // Highlight selected
    document.querySelectorAll('.time-slot').forEach(slot => {
        if (slot.textContent.includes(label)) {
            slot.classList.add('selected');
        } else {
            slot.classList.remove('selected');
        }
    });
    
    showToast('Time Slot Selected', label, 'success');
}

function closeAvailabilityModal() {
    // Close availability modal
    const availabilityModal = document.getElementById('availabilityModal');
    availabilityModal.classList.remove('show');
    setTimeout(() => availabilityModal.classList.add('hidden'), 300);
    
    // Show appointment modal again
    const appointmentModal = document.getElementById('appointmentModal');
    appointmentModal.style.display = '';
    appointmentModal.classList.remove('hidden');
    appointmentModal.classList.add('show');
}

function proceedToScheduling() {
    // Close availability modal
    const availabilityModal = document.getElementById('availabilityModal');
    availabilityModal.classList.remove('show');
    setTimeout(() => availabilityModal.classList.add('hidden'), 300);
    
    // Show appointment modal again
    setTimeout(() => {
        const appointmentModal = document.getElementById('appointmentModal');
        appointmentModal.style.display = '';
        appointmentModal.classList.remove('hidden');
        appointmentModal.classList.add('show');
        
        // Pre-fill form with selected values
        const date = document.getElementById('availabilityDate').value;
        
        if (selectedAvailability.doctor) {
            document.getElementById('appointmentDoctor').value = selectedAvailability.doctor.id;
        }
        
        if (selectedAvailability.room) {
            document.getElementById('appointmentRoom').value = selectedAvailability.room.id;
        }
        
        if (selectedAvailability.timeSlot && date) {
            // Set start time
            const startDateTime = `${date}T${selectedAvailability.timeSlot.start}`;
            document.getElementById('appointmentStartTime').value = startDateTime;
            
            // Set end time from the selected slot
            const endDateTime = `${date}T${selectedAvailability.timeSlot.end}`;
            document.getElementById('appointmentEndTime').value = endDateTime;
        }
        
        // Reset selections
        selectedAvailability = { doctor: null, room: null, timeSlot: null };
    }, 300);
}

// Load rooms for dropdown
async function loadRoomsForDropdown() {
    try {
        const response = await fetch(API_BASE + 'appointments.php?action=check_availability&date=' + new Date().toISOString().split('T')[0]);
        const data = await response.json();
        const select = document.getElementById('appointmentRoom');
        
        // Clear existing options except the first one
        select.innerHTML = '<option value="">Select Room (Optional)</option>';
        
        if (data.available_rooms && Array.isArray(data.available_rooms)) {
            data.available_rooms.forEach(room => {
                const option = document.createElement('option');
                option.value = room.id;
                option.textContent = `${room.room_number} - ${room.room_name}`;
                select.appendChild(option);
            });
        }
    } catch (err) {
        console.error('Error loading rooms for dropdown:', err);
    }
}

// Modal Functions
function hideModal() {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.classList.remove('show');
        setTimeout(() => modal.classList.add('hidden'), 300);
    });
    currentEditId = null;
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        hideModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') hideModal();
});

// =============================================================================
// M-PESA PAYMENT FUNCTIONS
// =============================================================================

/**
 * Show M-Pesa payment modal
 */
function showMpesaPayment(billingId, patientName, amount) {
    document.getElementById('mpesaBillingId').value = billingId;
    document.getElementById('mpesaPatientName').textContent = patientName;
    document.getElementById('mpesaBillAmount').textContent = formatCurrency(amount);
    document.getElementById('mpesaPhone').value = '';
    document.getElementById('mpesaStatus').classList.add('hidden');
    
    const modal = document.getElementById('mpesaModal');
    modal.classList.remove('hidden');
    setTimeout(() => modal.classList.add('show'), 10);
}

/**
 * Process M-Pesa payment
 */
async function processMpesaPayment(e) {
    e.preventDefault();
    
    const form = e.target;
    const billingId = document.getElementById('mpesaBillingId').value;
    const phoneNumber = document.getElementById('mpesaPhone').value;
    const payBtn = document.getElementById('mpesaPayBtn');
    const statusDiv = document.getElementById('mpesaStatus');
    const statusMsg = statusDiv.querySelector('.status-message');
    
    // Disable button
    payBtn.disabled = true;
    payBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    
    // Show status
    statusDiv.classList.remove('hidden');
    statusMsg.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Initiating payment request...';
    statusMsg.className = 'status-message status-info';
    
    try {
        const response = await fetch(API_BASE + 'mpesa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                action: 'initiate_payment',
                billing_id: billingId,
                phone_number: phoneNumber
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            statusMsg.innerHTML = `
                <i class="fas fa-check-circle"></i> 
                <strong>Payment request sent!</strong><br>
                <small>${result.customer_message || 'Check your phone for M-Pesa prompt and enter your PIN to complete payment.'}</small>
            `;
            statusMsg.className = 'status-message status-success';
            
            // Reset button
            payBtn.disabled = false;
            payBtn.innerHTML = '<i class="fas fa-mobile-alt"></i> Send Payment Request';
            
            // Start polling for payment status
            if (result.checkout_request_id) {
                pollMpesaStatus(result.checkout_request_id, billingId);
            }
            
            showToast('Payment Request Sent', 'Check your phone for M-Pesa prompt', 'success');
            
        } else {
            // Don't show persistent error banner - just show toast
            // The polling will handle success/failure properly
            statusMsg.innerHTML = `
                <i class="fas fa-info-circle"></i> 
                <strong>Payment initiated:</strong> Checking status...
            `;
            statusMsg.className = 'status-message status-info';
            
            payBtn.disabled = false;
            payBtn.innerHTML = '<i class="fas fa-mobile-alt"></i> Send Payment Request';
            
            showToast('Payment Status', result.message || 'Checking payment status...', 'info');
            
            // Still start polling to check actual status
            pollMpesaStatus(result.checkout_request_id, billingId);
        }
        
    } catch (err) {
        statusMsg.innerHTML = `
            <i class="fas fa-exclamation-circle"></i> 
            <strong>Error:</strong> ${err.message}
        `;
        statusMsg.className = 'status-message status-error';
        
        payBtn.disabled = false;
        payBtn.innerHTML = '<i class="fas fa-mobile-alt"></i> Send Payment Request';
        
        showAlert('Error processing payment: ' + err.message, 'error');
    }
}

/**
 * Poll M-Pesa transaction status
 */
let mpesaPollingInterval = null;
function pollMpesaStatus(checkoutRequestId, billingId) {
    let pollCount = 0;
    const maxPolls = 20; // Poll for 60 seconds (20 x 3 seconds)
    
    // Clear any existing polling
    if (mpesaPollingInterval) {
        clearInterval(mpesaPollingInterval);
    }
    
    mpesaPollingInterval = setInterval(async () => {
        pollCount++;
        
        try {
            // Use check_status endpoint instead of query_status
            const response = await fetch(`${API_BASE}mpesa.php?action=check_status&billing_id=${billingId}`, {
                method: 'GET',
                credentials: 'same-origin'
            });
            
            const result = await response.json();
            
            if (result.success && result.status === 'paid') {
                // Payment successful
                clearInterval(mpesaPollingInterval);
                
                const statusDiv = document.getElementById('mpesaStatus');
                const statusMsg = statusDiv.querySelector('.status-message');
                statusMsg.innerHTML = `
                    <i class="fas fa-check-circle"></i> 
                    <strong>Payment completed successfully!</strong><br>
                    <small>Payment Date: ${result.payment_date || 'N/A'}</small>
                `;
                statusMsg.className = 'status-message status-success';
                
                showToast('Payment Successful', 'Bill has been marked as paid', 'success');
                
                // Refresh billing table
                setTimeout(() => {
                    hideModal();
                    loadBilling();
                }, 2000);
                
            } else if (result.success === false && (result.status === 'cancelled' || result.status === 'failed')) {
                // Payment failed or cancelled
                clearInterval(mpesaPollingInterval);
                
                const statusDiv = document.getElementById('mpesaStatus');
                const statusMsg = statusDiv.querySelector('.status-message');
                statusMsg.innerHTML = `
                    <i class="fas fa-info-circle"></i> 
                    <strong>Payment ${result.status}:</strong> ${result.message || 'Please try again'}
                `;
                statusMsg.className = 'status-message status-info';
                
                showToast('Payment Status', result.message || 'Payment was not completed', 'info');
            }
            
        } catch (err) {
            console.error('Error polling M-Pesa status:', err);
        }
        
        // Stop polling after max attempts
        if (pollCount >= maxPolls) {
            clearInterval(mpesaPollingInterval);
            console.log('M-Pesa polling timeout');
        }
        
    }, 3000); // Poll every 3 seconds
}

// Initialize the application
document.addEventListener('DOMContentLoaded', async function() {
    // Check if user is already logged in
    try {
        const response = await fetch(API_BASE + 'auth.php?action=me', { credentials: 'same-origin' });
        if (response.ok) {
            const user = await response.json();

            if (user && user.id) {
                currentUser = user;
                showMainApp();
                
                // Redirect based on role
                if (hasPermission('dashboard', 'view')) {
                    loadDashboard();
                    showSection('dashboard');
                } else {
                    // Staff goes to patients page
                    showSection('patients');
                    loadPatients();
                }
            } else {
                showLoginPage();
            }
        } else {
            showLoginPage();
        }
    } catch (err) {
        showLoginPage();
    }
    
    // Set default dates for reports
    const today = new Date();
    const firstDayEver = '2000-01-01';
    document.getElementById('reportDateFrom').value = firstDayEver;
    document.getElementById('reportDateTo').value = today.toISOString().split('T')[0];
});
