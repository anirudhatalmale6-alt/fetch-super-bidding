# COMPANY FLEET DASHBOARD - FINAL DELIVERABLES
## Complete Audit, Gap Analysis & Implementation Roadmap

**Date:** February 13, 2026  
**System:** Tagxi Logistics + Bidding Platform  
**Version:** 1.17 Extension

---

## 1. AUDIT REPORT SUMMARY

### Section A — Already Implemented ✅

**Database (21 Tables):**
- companies, trucking_companies, trucking_hubs, supported_routes
- request_packages, request_legs, trucking_goods_items
- interstate_bids, tracking_updates, inspection_photos
- goods_status_updates, goods_fee_notifications, company_banners
- products, banners, shop_orders, shop_order_items, carts
- product_categories, order_stages, stage_payments, admin_action_logs

**Models (15 Models):**
- TruckingCompany, TruckingHub, SupportedRoute, GoodsItem, InterstateBid
- RequestPackage, RequestLeg, TrackingUpdate, InspectionPhoto
- CompanyBanner, OrderStage, StagePayment, GoodsStatusUpdate
- GoodsFeeNotification, Owner

**Controllers (9 Controllers):**
- GoodsController, InterstateBiddingController, InspectionController
- InterstateDeliveryController, TruckingCompanyAdminController
- TrackingController, FinalCostController, PaymentController
- OwnerController

**Views (9 Views):**
- company/goods/index, pending, pricing, show
- company/shop/index, cart, checkout, orders, order_detail

**API Endpoints (Complete):**
- All interstate bidding APIs
- All inspection APIs
- All tracking APIs
- All final cost APIs

### Section B — Partially Implemented ◑

| Feature | Current State |
|---------|---------------|
| Dashboard stats | Basic stats only |
| Profile management | Admin-only, no self-service |
| Hub management | Model exists, no UI |
| Route management | Model exists, no UI |
| Bidding interface | API only, no web UI |
| Shop cart/checkout | Views exist, logic incomplete |
| Banner display | Model exists, no display component |

### Section C — Missing ❌

| Feature | Impact |
|---------|--------|
| Dashboard Controller | HIGH - No overview dashboard |
| Notification System | HIGH - No in-app notifications |
| Profile Controller | MEDIUM - No self-service |
| Hub/Route UI | MEDIUM - Management interfaces |
| Bidding Web UI | HIGH - Companies need web interface |
| Banner Display | MEDIUM - Marketing banners |

### Section D — Safe Extension Points

1. **GoodsController** - Can add dashboard(), profile() methods
2. **New Controllers** - Dashboard, Profile, Notification, Hub, Route, Bid
3. **Routes** - Extend existing company.php route group
4. **Views** - Create new directories under resources/views/company/

### Section E — Risks

| Risk | Mitigation |
|------|------------|
| Data isolation | Use global scopes forCompany() |
| API/WEB duplication | Reuse API logic in Web controllers |
| Permission leaks | Add middleware checks |

---

## 2. GAP ANALYSIS TABLE

| Module | Features | Complete | Status |
|--------|----------|----------|--------|
| Core Company Panel | 7 | 4 (57%) | ◑ Partial |
| Goods Management | 8 | 5 (63%) | ◑ Partial |
| Interstate Module | 9 | 9 (100%) | ✅ Complete |
| Bidding Module | 6 | 5 (83%) | ◑ Partial |
| Shop Module | 7 | 4 (57%) | ◑ Partial |
| Banner Module | 4 | 2 (50%) | ◑ Partial |
| Operations Module | 5 | 0 (0%) | ❌ Missing |

**OVERALL: 58% Complete**

---

## 3. DASHBOARD MODULE LIST

### Core Modules

| Module | Description | Priority |
|--------|-------------|----------|
| Dashboard | Stats, charts, recent activity | HIGH |
| Profile | Self-service profile management | MEDIUM |
| Goods | List, search, filter, detail goods | HIGH |
| Bidding | View requests, submit/manage bids | HIGH |
| Inspection | Accept goods, measurements, fees | HIGH |
| Tracking | Update tracking, add notes | MEDIUM |
| Shop | Browse products, cart, checkout | MEDIUM |
| Notifications | In-app notification center | HIGH |
| Banners | Display marketing banners | MEDIUM |
| Hubs | Manage hub locations | MEDIUM |
| Routes | Manage supported routes | MEDIUM |
| Settings | Notification prefs, company settings | LOW |

---

## 4. DATABASE CHANGES

### New Tables Required

```sql
-- Company Notifications
CREATE TABLE company_notifications (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    owner_id BIGINT NOT NULL,
    trucking_company_id BIGINT,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    type VARCHAR(50),
    is_read BOOLEAN DEFAULT FALSE,
    link VARCHAR(500),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES owners(id),
    FOREIGN KEY (trucking_company_id) REFERENCES trucking_companies(id),
    INDEX idx_owner_read (owner_id, is_read),
    INDEX idx_created_at (created_at)
);

-- Company Alerts (High Priority)
CREATE TABLE company_alerts (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    owner_id BIGINT NOT NULL,
    alert_type VARCHAR(50),
    severity ENUM('low', 'medium', 'high', 'critical'),
    title VARCHAR(255),
    message TEXT,
    action_link VARCHAR(500),
    dismissed_at TIMESTAMP,
    created_at TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES owners(id),
    INDEX idx_owner_dismissed (owner_id, dismissed_at)
);

-- Company Settings
CREATE TABLE company_settings (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    owner_id BIGINT NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES owners(id),
    UNIQUE INDEX idx_owner_key (owner_id, setting_key)
);
```

### Table Modifications

```sql
-- Add to trucking_companies
ALTER TABLE trucking_companies 
ADD COLUMN bidding_restricted BOOLEAN DEFAULT FALSE,
ADD COLUMN account_locked_until TIMESTAMP NULL,
ADD COLUMN show_shop_section BOOLEAN DEFAULT TRUE,
ADD COLUMN dashboard_preferences JSON;

-- Add to owners
ALTER TABLE owners 
ADD COLUMN settings JSON,
ADD COLUMN logo VARCHAR(255);

-- Add indexes for performance
CREATE INDEX idx_goods_company_status ON trucking_goods_items(trucking_company_id, status);
CREATE INDEX idx_goods_company_created ON trucking_goods_items(trucking_company_id, created_at);
CREATE INDEX idx_bids_company_status ON interstate_bids(company_id, status);
```

---

## 5. MODELS TO MODIFY/CREATE

### Create New Models

```php
// app/Models/Interstate/CompanyNotification.php
class CompanyNotification extends Model
{
    protected $fillable = ['owner_id', 'trucking_company_id', 'title', 'message', 'type', 'is_read', 'link'];
    
    public function owner() { return $this->belongsTo(Owner::class); }
    public function company() { return $this->belongsTo(TruckingCompany::class, 'trucking_company_id'); }
    
    public function scopeUnread($query) { return $query->where('is_read', false); }
    public function scopeForOwner($query, $ownerId) { return $query->where('owner_id', $ownerId); }
}

// app/Models/Interstate/CompanyAlert.php
class CompanyAlert extends Model
{
    protected $fillable = ['owner_id', 'alert_type', 'severity', 'title', 'message', 'action_link', 'dismissed_at'];
    
    public function scopeActive($query) { return $query->whereNull('dismissed_at'); }
    public function scopeCritical($query) { return $query->where('severity', 'critical'); }
}

// app/Models/Interstate/CompanySetting.php
class CompanySetting extends Model
{
    protected $fillable = ['owner_id', 'setting_key', 'setting_value'];
}
```

### Extend Existing Models

```php
// app/Models/Admin/Owner.php - Add relationships
public function notifications() { return $this->hasMany(CompanyNotification::class); }
public function alerts() { return $this->hasMany(CompanyAlert::class); }
public function settings() { return $this->hasMany(CompanySetting::class); }

// app/Models/Interstate/TruckingCompany.php - Add scopes
public function scopeCanBid($query) {
    return $query->where('bidding_restricted', false)
                 ->where(function($q) {
                     $q->whereNull('account_locked_until')
                       ->orWhere('account_locked_until', '<', now());
                 });
}
```

---

## 6. CONTROLLERS TO MODIFY/CREATE

### Create New Controllers

| Controller | Methods | Purpose |
|------------|---------|---------|
| DashboardController | index | Main dashboard with stats |
| ProfileController | edit, update, changePassword, updatePassword, documents, uploadDocument, settings, updateSettings | Self-service profile |
| NotificationController | index, unreadCount, recent, markAsRead, markAllAsRead, destroy, preferences, updatePreferences | Notification center |
| HubController | index, create, store, edit, update, destroy | Hub management |
| RouteController | index, create, store, edit, update, destroy | Route management |
| CompanyBidController | index, available, show, submit, update, withdraw, history | Bidding web UI |
| BannerController | index, display | Banner display |

### Modify Existing Controllers

| Controller | Add Methods |
|------------|-------------|
| GoodsController | dashboard(), search(), filter() |

---

## 7. APIs NEEDED

### New API Endpoints

```php
// Dashboard APIs
GET    /api/v1/company/dashboard/stats
GET    /api/v1/company/dashboard/recent-activity
GET    /api/v1/company/dashboard/charts

// Notification APIs
GET    /api/v1/company/notifications
POST   /api/v1/company/notifications/{id}/read
POST   /api/v1/company/notifications/read-all
GET    /api/v1/company/notifications/unread-count

// Bidding Web APIs
GET    /api/v1/company/bids/available
POST   /api/v1/company/bids/submit
POST   /api/v1/company/bids/{id}/update
POST   /api/v1/company/bids/{id}/withdraw
GET    /api/v1/company/bids/history

// Hub/Route APIs
GET    /api/v1/company/hubs
POST   /api/v1/company/hubs
PUT    /api/v1/company/hubs/{id}
DELETE /api/v1/company/hubs/{id}
GET    /api/v1/company/routes
POST   /api/v1/company/routes
PUT    /api/v1/company/routes/{id}
DELETE /api/v1/company/routes/{id}
```

---

## 8. PERMISSIONS MATRIX

| Permission | last_mile | interstate | both | admin |
|------------|-----------|------------|------|-------|
| View Dashboard | ✅ | ✅ | ✅ | ✅ |
| Manage Goods | ✅ | ✅ | ✅ | ✅ |
| Submit Bids | ❌ | ✅ | ✅ | ❌ |
| Inspection Tools | ❌ | ✅ | ✅ | ✅ |
| View Shop | ✅ | ✅ | ✅ | ✅ |
| Manage Hubs | ❌ | ✅ | ✅ | ✅ |
| Manage Routes | ❌ | ✅ | ✅ | ✅ |
| View All Companies | ❌ | ❌ | ❌ | ✅ |
| Approve Companies | ❌ | ❌ | ❌ | ✅ |
| Restrict Bidding | ❌ | ❌ | ❌ | ✅ |
| Override Orders | ❌ | ❌ | ❌ | ✅ |

---

## 9. UI COMPONENT LIST

### Blade Views to Create

```
resources/views/company/
├── dashboard/
│   ├── index.blade.php          # Main dashboard
│   └── partials/
│       ├── stats-cards.blade.php
│       ├── charts.blade.php
│       ├── recent-activity.blade.php
│       └── banners.blade.php
├── profile/
│   ├── edit.blade.php           # Profile edit form
│   ├── change-password.blade.php
│   ├── documents.blade.php
│   └── settings.blade.php
├── hubs/
│   ├── index.blade.php
│   ├── create.blade.php
│   └── edit.blade.php
├── routes/
│   ├── index.blade.php
│   ├── create.blade.php
│   └── edit.blade.php
├── bids/
│   ├── index.blade.php          # Available requests
│   ├── create.blade.php         # Submit bid form
│   ├── history.blade.php        # My bids
│   └── show.blade.php           # Bid detail
├── notifications/
│   ├── index.blade.php
│   └── preferences.blade.php
├── goods/
│   └── partials/
│       ├── search-form.blade.php
│       └── filter-dropdown.blade.php
└── layouts/
    ├── app.blade.php
    ├── partials/
    │   ├── sidebar.blade.php
    │   ├── header.blade.php
    │   └── footer.blade.php
    └── components/
        ├── banner-carousel.blade.php
        ├── notification-dropdown.blade.php
        └── alert-banner.blade.php
```

---

## 10. EVENTS NEEDED

```php
// Company Dashboard Events
app/Events/Company/DashboardStatsUpdated.php
app/Events/Company/CompanyProfileUpdated.php

// Bidding Events (Web)
app/Events/Company/BidSubmittedWeb.php
app/Events/Company/BidUpdatedWeb.php
app/Events/Company/BidWithdrawnWeb.php

// Notification Events
app/Events/Company/NotificationCreated.php
app/Events/Company/NotificationRead.php
app/Events/Company/AlertCreated.php
app/Events/Company/AlertDismissed.php

// Goods Events
app/Events/Company/GoodsStatusUpdatedByCompany.php
app/Events/Company/InspectionCompleted.php
app/Events/Company/TrackingNoteAdded.php
```

---

## 11. QUEUE JOBS

```php
// Notification Jobs
app/Jobs/Company/SendCompanyNotification.php
app/Jobs/Company/SendCompanyAlert.php
app/Jobs/Company/ProcessNotificationDigest.php

// Sync Jobs
app/Jobs/Company/SyncCompanyToFirebase.php
app/Jobs/Company/SyncStatsToCache.php

// Report Jobs
app/Jobs/Company/GenerateDailyReport.php
app/Jobs/Company/GenerateWeeklySummary.php

// Maintenance Jobs
app/Jobs/Company/CleanupOldNotifications.php
app/Jobs/Company/ExpirePendingAlerts.php
```

---

## 12. RISKS & MITIGATION

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Data isolation breach | HIGH | LOW | Use forCompany() global scope on all queries |
| Performance degradation | MEDIUM | MEDIUM | Add caching, database indexes, eager loading |
| Unauthorized bidding | HIGH | LOW | Check bidding_restricted flag before allowing bids |
| File upload abuse | MEDIUM | MEDIUM | Validate file types, sizes, scan for malware |
| Notification spam | LOW | HIGH | Rate limit notifications, batch digest emails |
| API/Web duplication | MEDIUM | MEDIUM | Create shared service classes for business logic |
| Cache invalidation issues | MEDIUM | MEDIUM | Use cache tags, implement proper cache clearing |

---

## 13. DEPLOYMENT PLAN

### Phase 1: Core Infrastructure (Week 1)
- [ ] Create database migrations
- [ ] Create new models
- [ ] Create DashboardController + View
- [ ] Create ProfileController + Views
- [ ] Update routes
- [ ] Test basic functionality

### Phase 2: Operations Module (Week 1-2)
- [ ] Create NotificationController + Views
- [ ] Create notification tables
- [ ] Implement real-time notification polling
- [ ] Create alert system
- [ ] Add notification preferences

### Phase 3: Bidding Web Interface (Week 2)
- [ ] Create CompanyBidController
- [ ] Create bidding views
- [ ] Connect to existing API logic
- [ ] Add bid notifications
- [ ] Test bid workflow

### Phase 4: Hub & Route Management (Week 3)
- [ ] Create HubController + Views
- [ ] Create RouteController + Views
- [ ] Add CRUD operations
- [ ] Add validation
- [ ] Test management workflows

### Phase 5: Shop Enhancement (Week 3)
- [ ] Complete cart logic
- [ ] Complete checkout logic
- [ ] Add delivery request workflow
- [ ] Test purchase flow

### Phase 6: Banner & Polish (Week 4)
- [ ] Create BannerController
- [ ] Add banner display component
- [ ] Add caching
- [ ] Performance optimization
- [ ] Security audit
- [ ] Final testing

---

## 14. ROLLBACK PLAN

### Database Rollback
```sql
-- Remove new columns
ALTER TABLE trucking_companies 
DROP COLUMN bidding_restricted,
DROP COLUMN account_locked_until,
DROP COLUMN show_shop_section,
DROP COLUMN dashboard_preferences;

ALTER TABLE owners 
DROP COLUMN settings,
DROP COLUMN logo;

-- Drop new tables
DROP TABLE IF EXISTS company_notifications;
DROP TABLE IF EXISTS company_alerts;
DROP TABLE IF EXISTS company_settings;

-- Remove indexes
DROP INDEX idx_goods_company_status ON trucking_goods_items;
DROP INDEX idx_goods_company_created ON trucking_goods_items;
DROP INDEX idx_bids_company_status ON interstate_bids;
```

### Code Rollback
```bash
# Revert to previous git commit
git log --oneline -10  # View recent commits
git revert HEAD~N      # Revert N commits

# Or reset to specific tag
git checkout tags/v1.17

# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Firebase Rollback
```javascript
// Revert Firebase rules if needed
// Revert Firebase functions if deployed
```

---

## SUMMARY

**Current Completion:** 58%  
**Target Completion:** 100%  
**Estimated Effort:** 4 weeks  
**Critical Gaps:** Dashboard, Bidding UI, Notifications, Banner Display  
**Risk Level:** LOW (with proper testing)

**Recommended Priority:**
1. **Week 1:** Dashboard + Profile + Notifications
2. **Week 2:** Bidding Web Interface
3. **Week 3:** Hub/Route Management + Shop Completion
4. **Week 4:** Banner + Performance + Security

---

**END OF DELIVERABLES**
