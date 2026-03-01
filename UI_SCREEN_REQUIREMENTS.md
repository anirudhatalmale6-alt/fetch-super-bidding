# FETCH Platform - UI Screen Requirements

**Document Version:** 1.0  
**Date:** 2026-03-01  
**Project:** FETCH Platform Extension (Tagxi Super Bidding)

---

## Executive Summary

This document outlines all UI screens required for the FETCH Platform across four platforms:
1. **Web Frontend** (Public-facing website)
2. **Admin Backend** (Super Admin panel)
3. **Company Dashboard** (Transport company owner portal)
4. **Flutter User App** (Mobile app for customers)

**Legend:**
- ✅ = Screen exists and is complete
- 🟡 = Screen exists but needs updates
- ❌ = Screen needs to be created
- 🔶 = Screen is partially implemented

---

## 1. WEB FRONTEND (Public Website)

### 1.1 Core Pages

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Homepage | ✅ | High | Has video slider, needs FETCH branding |
| About Us | ❌ | Medium | Company information page |
| Services | ❌ | Medium | List of logistics services |
| Contact Us | ✅ | High | Exists, verify form works |
| FAQ | ❌ | Medium | Frequently asked questions |
| Terms & Conditions | ✅ | High | Exists |
| Privacy Policy | ✅ | High | Exists |
| Safety Information | ✅ | Medium | Exists |

### 1.2 Shop Module (E-commerce)

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Shop Landing | ✅ | High | `shop.blade.php` exists |
| Product Grid | ✅ | High | With filters and search |
| Product Detail | ✅ | High | `shop_details.blade.php` |
| Product Category View | 🟡 | Medium | Filter by category |
| Cart | ❌ | High | **NEEDS CREATION** |
| Checkout | ❌ | High | **NEEDS CREATION** |
| Order Confirmation | ❌ | High | **NEEDS CREATION** |
| Order History | ❌ | Medium | **NEEDS CREATION** |
| Payment Success/Failure | ❌ | High | **NEEDS CREATION** |

### 1.3 User Account (Web)

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Login | ❌ | High | **NEEDS CREATION** |
| Register | ❌ | High | **NEEDS CREATION** |
| Forgot Password | ❌ | High | **NEEDS CREATION** |
| User Dashboard | ❌ | High | **NEEDS CREATION** |
| Profile Management | ❌ | Medium | **NEEDS CREATION** |
| My Orders | ❌ | High | Shop + Logistics orders |
| My Shipments | ❌ | High | Package tracking |
| Payment Methods | ❌ | Medium | **NEEDS CREATION** |
| Address Book | ❌ | Medium | **NEEDS CREATION** |

### 1.4 Logistics (Web)

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Book Delivery | ❌ | High | **NEEDS CREATION** |
| Metro Booking | ❌ | High | Within-city delivery |
| Interstate Booking | ❌ | High | Cross-state delivery |
| Booking Confirmation | ❌ | High | **NEEDS CREATION** |
| Track Shipment | ❌ | High | **NEEDS CREATION** |
| Shipment Details | ❌ | High | Full tracking timeline |
| Rate Calculator | ❌ | Medium | Price estimation tool |
| Driver/Rider Signup | ✅ | Medium | `driver.blade.php` exists |

### 1.5 Company Pages

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Company Login | ✅ | High | `company-login.blade.php` |
| Company Registration | ❌ | High | **NEEDS CREATION** |
| Fleet Owner Signup | ❌ | Medium | **NEEDS CREATION** |

---

## 2. ADMIN BACKEND (Super Admin Panel)

### 2.1 Dashboard & Analytics

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Admin Dashboard | ✅ | High | `index.blade.php` exists |
| Shop Analytics | ❌ | High | **NEEDS CREATION** |
| Logistics Analytics | ❌ | High | **NEEDS CREATION** |
| Revenue Reports | ❌ | Medium | **NEEDS CREATION** |
| User Analytics | ❌ | Medium | **NEEDS CREATION** |

### 2.2 Shop Management (E-commerce)

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Products List | ✅ | High | `market/index.blade.php` |
| Create Product | ✅ | High | `market/create.blade.php` |
| Edit Product | ✅ | High | `market/edit.blade.php` |
| Product Categories | ❌ | High | **NEEDS CREATION** |
| Shop Orders | ❌ | High | **NEEDS CREATION** |
| Order Details | ❌ | High | **NEEDS CREATION** |
| Order Fulfillment | ❌ | High | **NEEDS CREATION** |
| Inventory Management | ❌ | Medium | **NEEDS CREATION** |
| Discount/Coupon Management | ❌ | Medium | **NEEDS CREATION** |
| Shop Settings | ❌ | Medium | **NEEDS CREATION** |

### 2.3 Banner Management

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Banners List | ✅ | High | `banners/index.blade.php` |
| Create Banner | ✅ | High | `banners/create.blade.php` |
| Edit Banner | ✅ | High | `banners/edit.blade.php` |
| Homepage Banners | 🟡 | High | Add target_type filter |
| Dashboard Banners | 🟡 | High | Add target_type filter |

### 2.4 Interstate Logistics Management

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Trucking Companies | 🔶 | High | `interstate/companies/` partial |
| Create Company | 🔶 | High | Needs completion |
| Edit Company | 🔶 | High | Needs completion |
| Company Details | ❌ | High | **NEEDS CREATION** |
| Hub Management | ❌ | High | **NEEDS CREATION** |
| Route Management | ❌ | High | **NEEDS CREATION** |
| Interstate Requests | ❌ | High | **NEEDS CREATION** |
| Request Details | ❌ | High | **NEEDS CREATION** |
| Bidding Management | ❌ | High | **NEEDS CREATION** |
| Pricing Rules | ❌ | Medium | **NEEDS CREATION** |
| Weight Classes | ❌ | Medium | **NEEDS CREATION** |

### 2.5 Package Management (Goods Hub)

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| All Packages | ❌ | High | **NEEDS CREATION** |
| Package Details | ❌ | High | **NEEDS CREATION** |
| Tracking Management | ❌ | High | **NEEDS CREATION** |
| Payment Status | ❌ | High | **NEEDS CREATION** |
| Insurance Management | ❌ | Medium | **NEEDS CREATION** |

### 2.6 Company Management

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Companies List | ✅ | High | `company/index.blade.php` |
| Create Company | ✅ | High | `company/create.blade.php` |
| Edit Company | ✅ | High | `company/update.blade.php` |
| Company Store | ✅ | High | `company/store/` |
| Company Documents | ✅ | Medium | Exists |
| Company Drivers | ✅ | Medium | Exists |
| Company Orders | ❌ | High | **NEEDS CREATION** |

### 2.7 User Management

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Users List | ✅ | High | `users/index.blade.php` |
| User Details | ✅ | High | Exists |
| User Orders | ❌ | Medium | **NEEDS CREATION** |
| User Shipments | ❌ | Medium | **NEEDS CREATION** |

### 2.8 Driver Management

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Drivers List | ✅ | High | `drivers/index.blade.php` |
| Driver Details | ✅ | High | Exists |
| Driver Documents | ✅ | High | Exists |
| Driver Ratings | ✅ | Medium | Exists |

### 2.9 Request/Trip Management

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| All Requests | ✅ | High | `request/index.blade.php` |
| Request Details | ✅ | High | `request/requestview.blade.php` |
| Cancelled Requests | ✅ | High | Exists |
| Scheduled Rides | ✅ | High | Exists |
| Delivery Requests | ✅ | High | Exists |
| Interstate Requests | ❌ | High | **NEEDS CREATION** |

### 2.10 Finance & Payments

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Payment Gateway Settings | ✅ | High | Exists |
| Transaction History | ❌ | High | **NEEDS CREATION** |
| Shop Order Payments | ❌ | High | **NEEDS CREATION** |
| Logistics Payments | ❌ | High | **NEEDS CREATION** |
| Driver Payments | ✅ | Medium | Exists |
| Company Payments | ✅ | Medium | Exists |
| Wallet Management | ✅ | Medium | Exists |

### 2.11 Settings & Configuration

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| General Settings | ✅ | High | Exists |
| CMS Pages | ✅ | High | Exists |
| Service Locations | ✅ | High | Exists |
| Zones | ✅ | High | Exists |
| Vehicle Types | ✅ | High | Exists |
| Goods Types | ✅ | Medium | Exists |
| Cancellation Reasons | ✅ | Medium | Exists |
| Promo Codes | ✅ | Medium | Exists |
| Notifications | ✅ | Medium | Exists |
| Roles & Permissions | ✅ | Medium | Exists |
| Mail Templates | ✅ | Low | Exists |
| SMS Settings | ✅ | Low | Exists |
| Firebase Settings | ✅ | Low | Exists |
| App Builds | ✅ | Low | Exists |

---

## 3. COMPANY DASHBOARD (Transport Company Portal)

### 3.1 Dashboard & Overview

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Company Dashboard | ✅ | High | `dashboard/index.blade.php` |
| Analytics Dashboard | ❌ | Medium | **NEEDS CREATION** |
| Revenue Reports | ❌ | Medium | **NEEDS CREATION** |

### 3.2 Bidding Management

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Available Bids | ✅ | High | `bids/available.blade.php` |
| Create Bid | ✅ | High | `bids/create.blade.php` |
| Bid History | ✅ | High | `bids/history.blade.php` |
| Active Bids | ❌ | High | **NEEDS CREATION** |

### 3.3 Goods/Package Management

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Goods List | ✅ | High | `goods/index.blade.php` |
| Pending Goods | ✅ | High | `goods/pending.blade.php` |
| Goods Pricing | ✅ | High | `goods/pricing.blade.php` |
| Goods Details | ✅ | High | `goods/show.blade.php` |
| Package List | ✅ | High | `packages/index.blade.php` |
| Package Details | ✅ | High | `packages/show.blade.php` |

### 3.4 Shop Module (B2B Store)

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Shop Home | ✅ | High | `shop/index.blade.php` |
| Product Detail | ❌ | High | **NEEDS CREATION** |
| Cart | ✅ | High | `shop/cart.blade.php` |
| Checkout | ✅ | High | `shop/checkout.blade.php` |
| Orders | ✅ | High | `shop/orders.blade.php` |
| Order Detail | ✅ | High | `shop/order_detail.blade.php` |

### 3.5 Fleet Management

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Fleet List | ❌ | High | **NEEDS CREATION** |
| Add Vehicle | ❌ | High | **NEEDS CREATION** |
| Vehicle Details | ❌ | Medium | **NEEDS CREATION** |
| Vehicle Documents | ❌ | Medium | **NEEDS CREATION** |
| Driver List | ❌ | High | **NEEDS CREATION** |
| Add Driver | ❌ | High | **NEEDS CREATION** |
| Driver Details | ❌ | Medium | **NEEDS CREATION** |
| Driver Documents | ❌ | Medium | **NEEDS CREATION** |

### 3.6 Route & Hub Management

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Hubs List | ✅ | High | `hubs/index.blade.php` |
| Hub Details | ❌ | Medium | **NEEDS CREATION** |
| Routes List | ✅ | High | `routes/index.blade.php` |
| Route Details | ❌ | Medium | **NEEDS CREATION** |

### 3.7 Profile & Settings

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Company Profile | ✅ | High | `profile/edit.blade.php` |
| Bank Details | ❌ | Medium | **NEEDS CREATION** |
| Notifications | ✅ | Medium | `notifications/index.blade.php` |
| Settings | ❌ | Medium | **NEEDS CREATION** |

### 3.8 Interstate Operations (For Trucking Companies)

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Interstate Dashboard | ❌ | High | **NEEDS CREATION** |
| Pending Legs | ❌ | High | **NEEDS CREATION** |
| Active Shipments | ❌ | High | **NEEDS CREATION** |
| Weight Verification | ❌ | High | **NEEDS CREATION** |
| Inspection Management | ❌ | High | **NEEDS CREATION** |
| Route Planning | ❌ | Medium | **NEEDS CREATION** |
| Truck Load Optimization | ❌ | Low | **NEEDS CREATION** |
| Completed Deliveries | ❌ | Medium | **NEEDS CREATION** |

---

## 4. FLUTTER USER APP (Mobile Customer App)

### 4.1 Authentication

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Splash Screen | ✅ | High | Exists |
| Onboarding | ✅ | High | Exists |
| Login | ✅ | High | `login/login.dart` |
| Register | ✅ | High | Exists |
| Forgot Password | ✅ | High | Exists |
| OTP Verification | ✅ | High | Exists |
| Biometric Setup | ❌ | Medium | **NEEDS CREATION** |

### 4.2 Home & Navigation

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Home Screen | ✅ | High | With map |
| Drawer/Navigation | ✅ | High | `navDrawer/nav_drawer.dart` |
| Notifications | ✅ | High | `NavigatorPages/notification.dart` |
| Search Location | ✅ | High | Exists |

### 4.3 Booking Flow

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Delivery Type Selection | ✅ | High | `delivery_type_selection_screen.dart` |
| Metro Booking | ✅ | High | Standard flow |
| Interstate Booking | ✅ | High | `interstate_request_flow.dart` |
| Sender Details | ✅ | High | Part of interstate flow |
| Recipient Details | ✅ | High | Part of interstate flow |
| Package Details | ✅ | High | Part of interstate flow |
| Pickup Location | ✅ | High | `pick_loc_select.dart` |
| Drop Location | ✅ | High | `drop_loc_select.dart` |
| Booking Confirmation | ✅ | High | `booking_confirmation.dart` |
| Bid Waiting | ✅ | High | `interstate_bid_waiting_screen.dart` |
| Company Bid List | ✅ | High | `company_bid_list_screen.dart` |
| Bid Card Detail | ✅ | High | `interstate_bid_card.dart` |
| Bid Confirmation | ✅ | High | `bid_confirmation_screen.dart` |

### 4.4 Tracking & Trip

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Driver Assignment | ✅ | High | Part of flow |
| On Trip | ✅ | High | `ongoingrides.dart` |
| Map View | ✅ | High | `map_page.dart` |
| Stage-Based Tracker | ✅ | High | `stage_based_tracker` |
| Tracking Timeline | 🟡 | High | Needs enhancement |
| Real-time Updates | ✅ | High | Via Firebase |
| Delivery Proof | ✅ | High | Exists |
| Review & Rating | ✅ | High | `review_page.dart` |
| Invoice | ✅ | High | `invoice.dart` |

### 4.5 Interstate-Specific Screens

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Final Cost Confirmation | ✅ | High | `final_cost_confirmation_screen.dart` |
| Weight Verification Display | ✅ | High | Part of final cost |
| Cost Comparison | ✅ | High | Estimated vs Final |
| Payment for Additional Cost | 🟡 | High | Needs integration |
| Rerouting Screen | ✅ | High | `rerouting_screen.dart` |
| Rerouting Confirmation | ✅ | High | Part of rerouting |
| Interstate Navigation | ✅ | High | `interstate_navigation.dart` |
| Interstate Payment | 🔶 | High | `interstate_payment_screen.dart` partial |
| Multi-leg Progress | ✅ | High | 7-stage tracker |
| Hub Handoff Display | ❌ | Medium | **NEEDS CREATION** |
| Company Info Display | ✅ | High | In bid card |
| Insurance Details | ❌ | Medium | **NEEDS CREATION** |

### 4.6 Shop Module (E-commerce)

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Shop Home | ❌ | High | **NEEDS CREATION** |
| Product Categories | ❌ | High | **NEEDS CREATION** |
| Product List | ❌ | High | **NEEDS CREATION** |
| Product Detail | ❌ | High | **NEEDS CREATION** |
| Cart | ❌ | High | **NEEDS CREATION** |
| Checkout | ❌ | High | **NEEDS CREATION** |
| Delivery Options | ❌ | High | Shop order delivery |
| Payment | ❌ | High | **NEEDS CREATION** |
| Order Confirmation | ❌ | High | **NEEDS CREATION** |
| Order History | ❌ | High | **NEEDS CREATION** |
| Order Detail | ❌ | High | **NEEDS CREATION** |
| Order Tracking | ❌ | High | Shop order tracking |

### 4.7 Package/Shipment Management

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| My Shipments | ❌ | High | **NEEDS CREATION** |
| Shipment List | ❌ | High | **NEEDS CREATION** |
| Shipment Detail | ❌ | High | **NEEDS CREATION** |
| Package Tracking | ❌ | High | **NEEDS CREATION** |
| Payment Status | ❌ | High | **NEEDS CREATION** |
| Insurance Claims | ❌ | Medium | **NEEDS CREATION** |

### 4.8 User Account

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Profile | ✅ | High | `NavigatorPages/editprofile.dart` |
| Edit Profile | ✅ | High | Exists |
| Change Password | ✅ | High | Exists |
| Addresses | ✅ | High | `NavigatorPages/fav_address.dart` |
| Payment Methods | ✅ | Medium | `NavigatorPages/paymentgateways.dart` |
| Wallet | ✅ | Medium | `NavigatorPages/walletpage.dart` |
| Transaction History | 🟡 | Medium | Needs shop transactions |
| Referral | ✅ | Low | `NavigatorPages/referral.dart` |
| Support/Help | ✅ | Medium | `NavigatorPages/support.dart` |
| FAQ | ✅ | Low | `NavigatorPages/faq.dart` |
| Chat with Support | ✅ | Medium | `chatPage/chat_page.dart` |
| Emergency/SOS | ✅ | High | `NavigatorPages/sos.dart` |
| Complaints | ✅ | Medium | `NavigatorPages/makecomplaint.dart` |
| Settings | ✅ | Medium | `NavigatorPages/settings.dart` |
| Language Selection | ✅ | Medium | `language/languages.dart` |
| Map Settings | ✅ | Low | `NavigatorPages/mapsettings.dart` |
| History | ✅ | Medium | `NavigatorPages/history.dart` |
| History Details | ✅ | Medium | `NavigatorPages/historydetails.dart` |
| Delete Account | ❌ | Medium | **NEEDS CREATION** |

---

## 5. FLUTTER DRIVER APP (Mobile Driver App)

### 5.1 Authentication

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Login | ❌ | High | **NEEDS CREATION** |
| Registration | ❌ | High | **NEEDS CREATION** |
| Document Upload | ❌ | High | **NEEDS CREATION** |
| Approval Pending | ❌ | High | **NEEDS CREATION** |

### 5.2 Dashboard & Earnings

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Driver Dashboard | ❌ | High | **NEEDS CREATION** |
| Earnings | ❌ | High | **NEEDS CREATION** |
| Earnings Details | ❌ | Medium | **NEEDS CREATION** |
| Weekly Summary | ❌ | Medium | **NEEDS CREATION** |
| Payment History | ❌ | Medium | **NEEDS CREATION** |

### 5.3 Trip Management

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Online/Offline Toggle | ❌ | High | **NEEDS CREATION** |
| Incoming Request | ❌ | High | **NEEDS CREATION** |
| Request Details | ❌ | High | **NEEDS CREATION** |
| Accept/Reject Request | ❌ | High | **NEEDS CREATION** |
| Navigation to Pickup | ❌ | High | **NEEDS CREATION** |
| Arrived at Pickup | ❌ | High | **NEEDS CREATION** |
| Package Pickup | ❌ | High | **NEEDS CREATION** |
| Navigation to Drop | ❌ | High | **NEEDS CREATION** |
| Arrived at Drop | ❌ | High | **NEEDS CREATION** |
| Delivery Confirmation | ❌ | High | **NEEDS CREATION** |
| Signature Capture | ❌ | High | **NEEDS CREATION** |
| Photo Upload | ❌ | High | **NEEDS CREATION** |
| Trip Complete | ❌ | High | **NEEDS CREATION** |

### 5.4 Interstate-Specific (Driver)

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Hub Drop-off | ❌ | High | **NEEDS CREATION** |
| Hub Pickup | ❌ | High | **NEEDS CREATION** |
| Handoff Confirmation | ❌ | High | **NEEDS CREATION** |
| Interstate Trip Details | ❌ | High | **NEEDS CREATION** |
| Multi-stop Route | ❌ | Medium | **NEEDS CREATION** |
| Leg Completion | ❌ | High | **NEEDS CREATION** |

### 5.5 Profile & Settings

| Screen | Status | Priority | Notes |
|--------|--------|----------|-------|
| Profile | ❌ | High | **NEEDS CREATION** |
| Vehicle Details | ❌ | High | **NEEDS CREATION** |
| Documents | ❌ | High | **NEEDS CREATION** |
| Settings | ❌ | Medium | **NEEDS CREATION** |
| Support | ❌ | Medium | **NEEDS CREATION** |

---

## 6. PRIORITY SUMMARY

### Critical Priority (Must Have - Phase 1)

**Web Frontend:**
- Cart, Checkout, Order Confirmation (Shop)
- Login, Register, Forgot Password (User Auth)
- User Dashboard, My Orders, My Shipments

**Admin Backend:**
- Shop Orders, Order Details, Order Fulfillment
- Interstate Requests, Request Details
- Hub Management, Route Management
- Transaction History (All types)

**Company Dashboard:**
- Product Detail view
- Fleet List, Add Vehicle, Driver List, Add Driver
- Interstate Dashboard, Pending Legs, Active Shipments

**Flutter App:**
- Shop Home, Product screens, Cart, Checkout (Shop)
- My Shipments, Shipment Detail, Package Tracking
- Interstate Payment integration

### High Priority (Phase 2)

**Web Frontend:**
- Book Delivery (Metro & Interstate)
- Track Shipment, Shipment Details

**Admin Backend:**
- Product Categories, Inventory Management
- Company Orders, User Orders/Shipments
- Bidding Management, Pricing Rules

**Company Dashboard:**
- Analytics Dashboard, Revenue Reports
- Vehicle & Driver Details
- Inspection Management, Weight Verification

**Flutter App:**
- Hub Handoff Display, Insurance Details
- Shop Order Tracking
- Delete Account

### Medium Priority (Phase 3)

**Web Frontend:**
- About Us, Services, FAQ pages
- Rate Calculator
- Payment Methods, Address Book

**Admin Backend:**
- Shop Analytics, Logistics Analytics
- Weight Classes, Discount Management
- Shop Settings

**Company Dashboard:**
- Route Planning, Truck Load Optimization
- Bank Details, Settings

**Flutter App:**
- Insurance Claims
- Biometric Setup

---

## 7. IMPLEMENTATION RECOMMENDATIONS

### Phase 1: Core E-commerce (Weeks 1-2)
1. Complete Shop screens for Web Frontend
2. Complete Shop Order management for Admin
3. Create Shop module for Flutter App
4. Implement Package/Shipment tracking for Flutter

### Phase 2: Interstate Logistics (Weeks 3-4)
1. Create Interstate management screens for Admin
2. Complete Interstate operations for Company Dashboard
3. Integrate Interstate Payment in Flutter
4. Create Hub Handoff screens

### Phase 3: User Management & Polish (Weeks 5-6)
1. Create User Auth screens for Web
2. Create Driver App screens
3. Add Analytics and Reports
4. Testing and refinement

---

## 8. NOTES

### Design Consistency
- All new screens should follow existing design patterns
- Use the same color scheme (FETCH branding)
- Maintain responsive design for web
- Follow Material Design for Flutter

### API Dependencies
- Shop module APIs need to be complete
- Package tracking APIs need to be complete
- Interstate management APIs need to be complete

### Testing Requirements
- Each screen needs unit tests
- Integration tests for user flows
- UI tests for responsive design
- E2E tests for complete booking flow

---

*End of Document*
