/**
 * API Integration Layer - KIUT-Eventrix Campus
 * 
 * This file provides JavaScript functions to work with localStorage instead of PHP backend
 * This enables the application to work without server requirements
 */

// Storage Configuration
const STORAGE_KEYS = {
    users: 'kiut_users',
    events: 'kiut_events', 
    notifications: 'kiut_notifications',
    currentUser: 'currentUser',
    authToken: 'authToken'
};

// Current user state
let currentUser = null;
let authToken = null;

/**
 * Initialize API with stored authentication
 */
function initAPI() {
    const storedUser = localStorage.getItem(STORAGE_KEYS.currentUser);
    const storedToken = localStorage.getItem(STORAGE_KEYS.authToken);
    
    if (storedUser && storedToken) {
        currentUser = JSON.parse(storedUser);
        authToken = storedToken;
        return true;
    }
    return false;
}

/**
 * Set authentication credentials
 */
function setAuth(user, token) {
    currentUser = user;
    authToken = token;
    localStorage.setItem(STORAGE_KEYS.currentUser, JSON.stringify(user));
    localStorage.setItem(STORAGE_KEYS.authToken, token);
}

/**
 * Clear authentication
 */
function clearAuth() {
    currentUser = null;
    authToken = null;
    localStorage.removeItem(STORAGE_KEYS.currentUser);
    localStorage.removeItem(STORAGE_KEYS.authToken);
}

/**
 * User Registration (localStorage)
 */
async function registerUser(userData) {
    try {
        const users = JSON.parse(localStorage.getItem(STORAGE_KEYS.users) || '[]');
        
        // Check if email already exists
        if (users.find(u => u.email === userData.email)) {
            return { success: false, message: 'Email already registered' };
        }
        
        // Create new user
        const newUser = {
            id: generateId(),
            ...userData,
            createdAt: new Date().toISOString(),
            isActive: true
        };
        
        users.push(newUser);
        localStorage.setItem(STORAGE_KEYS.users, JSON.stringify(users));
        
        const token = 'token_' + Date.now();
        setAuth(newUser, token);
        
        return { 
            success: true, 
            message: 'Registration successful',
            data: { user: newUser, token: token }
        };
        
    } catch (error) {
        return { success: false, message: 'Registration failed: ' + error.message };
    }
}

/**
 * User Login (localStorage)
 */
async function loginUser(email, password, role) {
    try {
        const users = JSON.parse(localStorage.getItem(STORAGE_KEYS.users) || '[]');
        
        const user = users.find(u => 
            u.email === email && 
            u.password === password && 
            u.role === role
        );
        
        if (!user) {
            return { success: false, message: 'Invalid credentials' };
        }
        
        if (!user.isActive) {
            return { success: false, message: 'Account is inactive' };
        }
        
        const token = 'token_' + Date.now();
        setAuth(user, token);
        
        return { 
            success: true, 
            message: 'Login successful',
            data: { user: user, token: token }
        };
        
    } catch (error) {
        return { success: false, message: 'Login failed: ' + error.message };
    }
}

/**
 * Generate unique ID
 */
function generateId() {
    return 'user_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
}

/**
 * Create Event (localStorage)
 */
async function createEvent(eventData) {
    try {
        const events = JSON.parse(localStorage.getItem(STORAGE_KEYS.events) || '[]');
        
        const newEvent = {
            id: generateId(),
            ...eventData,
            organizerId: currentUser.id,
            organizerName: currentUser.firstName + ' ' + currentUser.lastName,
            organizerEmail: currentUser.email,
            status: currentUser.role === 'admin' ? 'approved' : 'pending',
            participants: [],
            createdAt: new Date().toISOString(),
            updatedAt: new Date().toISOString()
        };
        
        events.push(newEvent);
        localStorage.setItem(STORAGE_KEYS.events, JSON.stringify(events));
        
        return { 
            success: true, 
            message: 'Event created successfully',
            data: newEvent
        };
        
    } catch (error) {
        return { success: false, message: 'Failed to create event: ' + error.message };
    }
}

/**
 * Get Events (localStorage)
 */
async function getEvents(filters = {}) {
    try {
        const events = JSON.parse(localStorage.getItem(STORAGE_KEYS.events) || '[]');
        
        let filteredEvents = events;
        
        // Apply filters
        if (filters.status) {
            filteredEvents = filteredEvents.filter(e => e.status === filters.status);
        }
        
        if (filters.organizerId) {
            filteredEvents = filteredEvents.filter(e => e.organizerId === filters.organizerId);
        }
        
        if (filters.category) {
            filteredEvents = filteredEvents.filter(e => e.category === filters.category);
        }
        
        // Sort by creation date (newest first)
        filteredEvents.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
        
        return { 
            success: true, 
            data: filteredEvents
        };
        
    } catch (error) {
        return { success: false, message: 'Failed to get events: ' + error.message };
    }
}

/**
 * Update Event (localStorage)
 */
async function updateEvent(eventId, eventData) {
    try {
        const events = JSON.parse(localStorage.getItem(STORAGE_KEYS.events) || '[]');
        const eventIndex = events.findIndex(e => e.id === eventId);
        
        if (eventIndex === -1) {
            return { success: false, message: 'Event not found' };
        }
        
        // Check permissions
        const event = events[eventIndex];
        if (currentUser.role !== 'admin' && event.organizerId !== currentUser.id) {
            return { success: false, message: 'Access denied' };
        }
        
        // Update event
        events[eventIndex] = {
            ...event,
            ...eventData,
            updatedAt: new Date().toISOString()
        };
        
        localStorage.setItem(STORAGE_KEYS.events, JSON.stringify(events));
        
        return { 
            success: true, 
            message: 'Event updated successfully',
            data: events[eventIndex]
        };
        
    } catch (error) {
        return { success: false, message: 'Failed to update event: ' + error.message };
    }
}

/**
 * Delete Event (localStorage)
 */
async function deleteEvent(eventId) {
    try {
        const events = JSON.parse(localStorage.getItem(STORAGE_KEYS.events) || '[]');
        const eventIndex = events.findIndex(e => e.id === eventId);
        
        if (eventIndex === -1) {
            return { success: false, message: 'Event not found' };
        }
        
        // Check permissions
        const event = events[eventIndex];
        if (currentUser.role !== 'admin' && event.organizerId !== currentUser.id) {
            return { success: false, message: 'Access denied' };
        }
        
        // Remove event
        events.splice(eventIndex, 1);
        localStorage.setItem(STORAGE_KEYS.events, JSON.stringify(events));
        
        return { 
            success: true, 
            message: 'Event deleted successfully'
        };
        
    } catch (error) {
        return { success: false, message: 'Failed to delete event: ' + error.message };
    }
}

/**
 * Make API request (fallback for future use)
 */
async function apiRequest(endpoint, method = 'GET', data = null, params = {}) {
    // For now, return mock response since we're using localStorage
    return { success: true, message: 'Using localStorage mode' };
}

/**
 * Create Notification (localStorage)
 */
async function createNotification(notificationData) {
    try {
        const notifications = JSON.parse(localStorage.getItem(STORAGE_KEYS.notifications) || '[]');
        
        const newNotification = {
            id: generateId(),
            ...notificationData,
            createdAt: new Date().toISOString(),
            read: false
        };
        
        notifications.push(newNotification);
        localStorage.setItem(STORAGE_KEYS.notifications, JSON.stringify(notifications));
        
        return { 
            success: true, 
            message: 'Notification created successfully',
            data: newNotification
        };
        
    } catch (error) {
        return { success: false, message: 'Failed to create notification: ' + error.message };
    }
}

/**
 * Get Notifications (localStorage)
 */
async function getNotifications(filters = {}) {
    try {
        const notifications = JSON.parse(localStorage.getItem(STORAGE_KEYS.notifications) || '[]');
        
        let filteredNotifications = notifications;
        
        // Apply filters
        if (filters.target) {
            filteredNotifications = filteredNotifications.filter(n => n.target === filters.target);
        }
        
        if (filters.read !== undefined) {
            filteredNotifications = filteredNotifications.filter(n => n.read === filters.read);
        }
        
        // Sort by creation date (newest first)
        filteredNotifications.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
        
        return { 
            success: true, 
            data: filteredNotifications
        };
        
    } catch (error) {
        return { success: false, message: 'Failed to get notifications: ' + error.message };
    }
}

/**
 * Initialize sample data
 */
function initializeSampleData() {
    // Check if data already exists
    const users = JSON.parse(localStorage.getItem(STORAGE_KEYS.users) || '[]');
    const events = JSON.parse(localStorage.getItem(STORAGE_KEYS.events) || '[]');
    
    if (users.length === 0) {
        // Create sample admin user
        const adminUser = {
            id: generateId(),
            firstName: 'Admin',
            lastName: 'User',
            email: 'admin@kiut.ac.tz',
            password: 'password',
            role: 'admin',
            department: 'IT',
            phone: '+255123456789',
            idNumber: 'ADMIN001',
            year: 'Administrator',
            isActive: true,
            createdAt: new Date().toISOString()
        };
        
        localStorage.setItem(STORAGE_KEYS.users, JSON.stringify([adminUser]));
    }
    
    if (events.length === 0) {
        localStorage.setItem(STORAGE_KEYS.events, JSON.stringify([]));
    }
}

/**
 * Authentication API
 */
const AuthAPI = {
    // Register user
    async register(userData) {
        const result = await apiRequest('auth.php?action=register', 'POST', userData);
        if (result.data.token) {
            setAuth(result.data.user, result.data.token);
        }
        return result;
    },
    
    // Login user
    async login(credentials) {
        const result = await apiRequest('auth.php?action=login', 'POST', credentials);
        if (result.data.token) {
            setAuth(result.data.user, result.data.token);
        }
        return result;
    },

    // Student access
    async studentAccess(regNo) {
        const regNoNormalized = regNo.trim().toUpperCase().replace(/\s+/g, '');
        // Check if using local mode or if server fetch is needed
        if (window.location.protocol !== 'file:') {
            try {
                const apiBase = new URL('../api/', window.location.href).href;
                const response = await fetch(`${apiBase}auth.php?action=student_access`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ reg_no: regNoNormalized })
                });
                const result = await response.json();
                if (result.success && result.data.token) {
                    setAuth(result.data.user, result.data.token);
                }
                return result;
            } catch (e) {
                console.warn('API student_access failed, using local storage mode:', e);
            }
        }

        // LocalStorage fallback
        const users = JSON.parse(localStorage.getItem(STORAGE_KEYS.users) || '[]');
        let student = users.find(u => 
            u.role === 'student' && 
            (u.idNumber === regNoNormalized || u.regNumber === regNoNormalized || u.reg_no === regNoNormalized)
        );

        if (!student) {
            // Auto-create student locally
            student = {
                id: 'user_' + Date.now(),
                firstName: 'Student',
                lastName: '(' + regNoNormalized + ')',
                name: 'Student (' + regNoNormalized + ')',
                email: regNoNormalized.toLowerCase().replace(/\//g, '.') + '@student.kiut.ac.tz',
                role: 'student',
                department: 'Computer Science',
                idNumber: regNoNormalized,
                regNumber: regNoNormalized,
                isActive: true,
                createdAt: new Date().toISOString()
            };
            users.push(student);
            localStorage.setItem(STORAGE_KEYS.users, JSON.stringify(users));
        }

        const token = 'demo-token-' + Date.now();
        setAuth(student, token);
        return {
            success: true,
            message: 'Login successful',
            data: { user: student, token: token }
        };
    },
    
    // Get current user
    async getCurrentUser() {
        const result = await apiRequest('auth.php?action=me', 'GET');
        return result;
    },
    
    // Update profile
    async updateProfile(profileData) {
        const result = await apiRequest('auth.php?action=update', 'PUT', profileData);
        return result;
    },
    
    // Change password
    async changePassword(passwordData) {
        const result = await apiRequest('auth.php?action=changePassword', 'POST', passwordData);
        return result;
    },
    
    // Logout
    async logout() {
        await apiRequest('auth.php?action=logout', 'POST');
        clearAuth();
    }
};

/**
 * Events API
 */
const EventsAPI = {
    // Get all events
    async getAllEvents() {
        return await apiRequest('events.php', 'GET');
    },
    
    // Get event by ID
    async getEvent(eventId) {
        return await apiRequest('events.php', 'GET', null, { action: 'get', id: eventId });
    },
    
    // Create event
    async createEvent(eventData) {
        return await apiRequest('events.php?action=create', 'POST', eventData);
    },
    
    // Update event
    async updateEvent(eventId, eventData) {
        return await apiRequest('events.php', 'PUT', eventData, { action: 'update', id: eventId });
    },
    
    // Delete event
    async deleteEvent(eventId) {
        return await apiRequest('events.php', 'DELETE', null, { action: 'delete', id: eventId });
    },
    
    // Register for event
    async registerForEvent(eventId) {
        return await apiRequest('events.php', 'POST', null, { action: 'register', id: eventId });
    },
    
    // Unregister from event
    async unregisterFromEvent(eventId) {
        return await apiRequest('events.php', 'POST', null, { action: 'unregister', id: eventId });
    },
    
    // Get calendar events
    async getCalendarEvents() {
        return await apiRequest('events.php', 'GET', null, { action: 'calendar' });
    }
};

/**
 * Users API
 */
const UsersAPI = {
    // Get all users (admin only)
    async getAllUsers() {
        return await apiRequest('users.php', 'GET');
    },
    
    // Get user by ID
    async getUser(userId) {
        return await apiRequest('users.php', 'GET', null, { action: 'get', id: userId });
    },
    
    // Create user (admin only)
    async createUser(userData) {
        return await apiRequest('users.php?action=create', 'POST', userData);
    },
    
    // Update user
    async updateUser(userId, userData) {
        return await apiRequest('users.php', 'PUT', userData, { action: 'update', id: userId });
    },
    
    // Delete user (admin only)
    async deleteUser(userId) {
        return await apiRequest('users.php', 'DELETE', null, { action: 'delete', id: userId });
    },
    
    // Toggle user status (admin only)
    async toggleUserStatus(userId) {
        return await apiRequest('users.php', 'POST', null, { action: 'toggleStatus', id: userId });
    },
    
    // Get user statistics (admin only)
    async getUserStatistics() {
        return await apiRequest('users.php', 'GET', null, { action: 'statistics' });
    },
    
    // Search users (admin only)
    async searchUsers(query) {
        return await apiRequest('users.php', 'GET', null, { action: 'search', q: query });
    }
};

/**
 * Notifications API
 */
const NotificationsAPI = {
    // Get user notifications
    async getNotifications(filters = {}) {
        return await apiRequest('notifications.php', 'GET', null, filters);
    },
    
    // Mark notification as read
    async markAsRead(notificationId) {
        return await apiRequest('notifications.php', 'PUT', null, { action: 'read', id: notificationId });
    },
    
    // Mark all notifications as read
    async markAllAsRead() {
        return await apiRequest('notifications.php', 'PUT', null, { action: 'readAll' });
    },
    
    // Delete notification
    async deleteNotification(notificationId) {
        return await apiRequest('notifications.php', 'DELETE', null, { action: 'delete', id: notificationId });
    },
    
    // Clear all notifications
    async clearAllNotifications() {
        return await apiRequest('notifications.php', 'DELETE', null, { action: 'clearAll' });
    },
    
    // Get notification statistics
    async getNotificationStatistics() {
        return await apiRequest('notifications.php', 'GET', null, { action: 'statistics' });
    }
};

/**
 * Approvals API
 */
const ApprovalsAPI = {
    // Get pending events (admin only)
    async getPendingEvents() {
        return await apiRequest('approvals.php', 'GET');
    },
    
    // Get all events (admin only)
    async getAllEvents() {
        return await apiRequest('approvals.php', 'GET', null, { action: 'all' });
    },
    
    // Approve event (admin only)
    async approveEvent(eventId) {
        return await apiRequest('approvals.php', 'POST', null, { action: 'approve', id: eventId });
    },
    
    // Reject event (admin only)
    async rejectEvent(eventId, reason) {
        return await apiRequest('approvals.php', 'POST', { reason }, { action: 'reject', id: eventId });
    },
    
    // Bulk approve events (admin only)
    async bulkApproveEvents(eventIds) {
        return await apiRequest('approvals.php', 'POST', { eventIds }, { action: 'bulkApprove' });
    },
    
    // Bulk reject events (admin only)
    async bulkRejectEvents(eventIds, reason) {
        return await apiRequest('approvals.php', 'POST', { eventIds, reason }, { action: 'bulkReject' });
    },
    
    // Get approval statistics (admin only)
    async getApprovalStatistics() {
        return await apiRequest('approvals.php', 'GET', null, { action: 'statistics' });
    },
    
    // Search events (admin only)
    async searchEvents(query, status = '') {
        return await apiRequest('approvals.php', 'GET', null, { action: 'search', q: query, status });
    }
};

/**
 * Utility functions
 */
function showToast(message, type = 'info') {
    // Create toast element if it doesn't exist
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        `;
        document.body.appendChild(toastContainer);
    }
    
    // Create toast
    const toast = document.createElement('div');
    toast.style.cssText = `
        background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : type === 'warning' ? '#ffc107' : '#007bff'};
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        min-width: 250px;
        animation: slideIn 0.3s ease;
        font-size: 14px;
        font-weight: 500;
    `;
    toast.textContent = message;
    
    toastContainer.appendChild(toast);
    
    // Remove toast after 3 seconds
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, 3000);
}

// Add animation styles
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);

/**
 * Check authentication status
 */
function checkAuth() {
    if (!authToken) {
        showToast('Please login to continue', 'warning');
        window.location.href = 'signin.html';
        return false;
    }
    return true;
}

/**
 * Format date
 */
function formatDate(dateString, format = 'YYYY-MM-DD') {
    const date = new Date(dateString);
    return date.toISOString().split('T')[0];
}

/**
 * Format time
 */
function formatTime(timeString) {
    const [hours, minutes] = timeString.split(':');
    return `${hours}:${minutes}`;
}

/**
 * Generate unique ID
 */
function generateId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
}

// Export for global use
window.API = {
    Auth: AuthAPI,
    Events: EventsAPI,
    Users: UsersAPI,
    Notifications: NotificationsAPI,
    Approvals: ApprovalsAPI,
    init: initAPI,
    setAuth: setAuth,
    clearAuth: clearAuth,
    checkAuth: checkAuth,
    showToast: showToast,
    formatDate: formatDate,
    formatTime: formatTime,
    generateId: generateId,
    currentUser: () => currentUser,
    authToken: () => authToken
};

// Initialize API on page load
document.addEventListener('DOMContentLoaded', function() {
    initAPI();
});
