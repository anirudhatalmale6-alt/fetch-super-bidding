# 🚕 FETCH-MarketSquare - Complete Design Redesign Master Prompt for Figma AI

---

## 📋 PROJECT OVERVIEW

**FETCH** is Nigeria's premier multi-service transportation and delivery platform. The platform serves as a comprehensive marketplace connecting customers with transportation and logistics services, while also operating an e-commerce store for equipment and supplies.

### Core Services

| Service | Description |
|---------|-------------|
| 🚖 **Taxi/Ride Booking** | Instant and scheduled rides (cars, bikes) |
| 🚛 **Goods/Delivery** | Local and interstate freight services |
| 🏪 **E-commerce Shop** | Equipment, supplies, and fleet products |
| 💰 **Bidding System** | Competitive bidding for logistics contracts |
| 🏢 **Company Portal** | Fleet owners & trucking company dashboard |
| 📡 **Dispatch Control** | Real-time ride/delivery management |

### Target Audience

- **End Users**: Passengers needing rides, businesses requiring deliveries
- **Drivers**: Taxi drivers, delivery riders, truck drivers
- **Fleet Owners**: Companies managing vehicle fleets
- **Trucking Companies**: Interstate logistics providers
- **Dispatch Operators**: Platform staff managing requests

---

## 🎨 DESIGN PHILOSOPHY & PRINCIPLES

### Overall Aesthetic

- **Modern, Professional & Trustworthy** - Reflects reliability for essential transportation services
- **Dark/Light Mode Ready** - Versatile color system accommodating both themes
- **Mobile-First Approach** - Responsive across all devices (mobile, tablet, desktop)
- **Map-Centric Experience** - Clear visualization of locations, routes, and driver positions
- **Card-Based UI** - Clean content organization using card components
- **Gradient Accents** - Primary brand gradient (#2bc9de → #4F46E5)
- **Clean Typography** - Sans-serif fonts (Inter, Poppins)
- **Micro-interactions** - Subtle animations for engagement

### Design System Requirements

#### Color Palette

```
PRIMARY COLORS
├── Primary Blue:      #4F46E5 (Indigo) - Main brand color
├── Primary Dark:      #4338CA         - Hover states
├── Secondary Orange:  #F97316         - CTAs and highlights
├── Accent Teal:       #14B8A6         - Success states
└── Brand Gradient:    #2bc9de → #4F46E5

NEUTRAL COLORS
├── Dark Background:   #0F172A
├── Card Background:   #FFFFFF
├── Light Background:  #F8FAFC
├── Border Color:      #E5E7EB
├── Text Primary:      #1F2937
└── Text Secondary:    #6B7280

STATUS COLORS
├── Success:           #22C55E (Green)
├── Warning:           #F59E0B (Amber)
├── Danger:            #EF4444 (Red)
├── Info:              #3B82F6 (Blue)
└── Pending:          #8B5CF6 (Purple)
```

#### Typography

| Element | Font | Weight | Size |
|---------|------|--------|------|
| Headings H1 | Inter/Poppins | 700-800 | 2.5rem (40px) |
| Headings H2 | Inter/Poppins | 700 | 2rem (32px) |
| Headings H3 | Inter/Poppins | 600 | 1.5rem (24px) |
| Headings H4 | Inter/Poppins | 600 | 1.25rem (20px) |
| Body Text | Inter/Poppins | 400-500 | 1rem (16px) |
| Small Text | Inter/Poppins | 400 | 0.875rem (14px) |
| Caption | Inter/Poppins | 400 | 0.75rem (12px) |

#### Spacing System (8pt Grid)

- Base unit: 8px
- Spacing scale: 4, 8, 12, 16, 24, 32, 48, 64, 80, 96px
- Card padding: 20-24px
- Section padding: 48-80px
- Grid gap: 16-32px

#### Border Radius

- Small: 4px (buttons, inputs)
- Medium: 8px (cards, panels)
- Large: 12px (modals, containers)
- XL: 16-20px (hero cards, featured items)
- Full: 9999px (pills, avatars, circular buttons)

#### Shadows

```css
--shadow-sm:   0 1px 2px rgba(0,0,0,0.05);
--shadow-md:   0 4px 6px rgba(0,0,0,0.07);
--shadow-lg:   0 10px 15px rgba(0,0,0,0.1);
--shadow-xl:   0 20px 25px rgba(0,0,0,0.15);
--shadow-card: 0 10px 40px rgba(0,0,0,0.08);
--shadow-hover: 0 20px 60px rgba(0,0,0,0.15);
```

---

## 📱 SCREEN REQUIREMENTS

### SECTION 1: PUBLIC WEBSITE (Landing Page to Shop)

#### 1.1 Landing Page (Home) - PRIORITY: HIGH

**Purpose**: Main marketing page that converts visitors into users (riders/drivers/buyers)

**Layout Structure**:
- Fixed navigation header (70px height)
- Full-width hero section (min-height: 500px)
- Feature highlights section
- How it works / Steps section
- App download CTA section
- Testimonials carousel
- Statistics/numbers section
- Newsletter signup
- Footer with links

**Navigation Components**:
- Logo (left-aligned)
- Menu links: Home, Shop, Driver App, Company Portal, Contact
- Login / Sign Up buttons (right-aligned)
- Language selector dropdown
- Mobile: Hamburger menu with slide-out drawer

**Hero Section Options**:
- Video slider with Ken Burns effect
- Image carousel with fade transitions
- Static gradient background with animated shapes
- Content: Headline, subheadline, 2 CTA buttons
- App download badges (App Store, Google Play)

**Feature Cards Section**:
- 3-column grid on desktop
- Icon + Title + Description per card
- Subtle hover lift effect
- Features: "Reliable Service", "Affordable Rates", "Secure Payments", "24/7 Support"

**How It Works Section**:
- 4-step horizontal timeline
- Numbered circles with icons
- Connecting line between steps
- Mobile: Vertical stacked layout

**Statistics Section**:
- 4-column grid
- Large numbers with counting animation
- Label below each number
- Metrics: Rides Completed, Active Drivers, Companies, Happy Users

**Footer Components**:
- 4-column layout
- Column 1: Logo, tagline, social icons
- Column 2: Quick Links
- Column 3: Services
- Column 4: Newsletter signup form
- Bottom bar: Copyright, Privacy, Terms, Compliance

---

#### 1.2 Public Shop Page - PRIORITY: HIGH

**Purpose**: E-commerce store for equipment and supplies (public facing)

**Layout Structure**:
- Shop hero banner with gradient background
- Sticky filter/search toolbar
- Product grid (main content)
- Sidebar filters (collapsible on mobile)

**Shop Hero**:
- Height: 400px
- Background: Gradient (#0F172A → #1E293B)
- Content: Title "Equipment Store", subtitle, category quick links
- Animated background shapes

**Search & Filter Toolbar** (sticky below nav):
- Search input with icon (max-width: 400px)
- Category filter buttons (pills)
- Sort dropdown (Latest, Price Low-High, Price High-Low, Name A-Z)
- View toggle (grid 4cols / 3cols / 2cols / list)
- Results count display

**Product Grid**:
- Responsive: 4 cols → 3 cols → 2 cols → 1 col
- Gap: 24-30px
- Infinite scroll or pagination

**Product Card Components**:
- Image container (220px height, hover zoom)
- Badge system (top-left):
  - Sale: Red badge with % off
  - New: Green "New" badge
  - Hot: Orange "Hot" badge
  - Featured: Purple badge
- Quick actions (top-right, appear on hover):
  - Quick View (eye icon)
  - Wishlist (heart icon)
  - Compare (arrows icon)
- Category tag (small, above title)
- Product title (2 lines max, truncate)
- Short description (2 lines max)
- Price: Current price (bold) + strikethrough original (if discount)
- Add to Cart button
- Rating stars (optional)

**Featured Products Section**:
- Dark themed section
- Horizontal scroll or grid
- "Featured" badge on cards

**New Arrivals Section**:
- Light themed
- "New Arrival" badge
- Grid layout

**Categories Section**:
- Visual category cards (180px height)
- Image background with overlay
- Category name + product count
- Hover: Scale up slightly

**Pagination**:
- Numbered buttons
- Previous/Next arrows
- "Load More" button option

---

#### 1.3 Product Detail Page - PRIORITY: HIGH

**Layout Structure**:
- Breadcrumb navigation
- Two-column layout (image gallery + details)
- Related products section
- Reviews section below

**Image Gallery**:
- Large main image (zoom on hover)
- Thumbnail strip below (4-6 images)
- Lightbox modal on click
- Mobile: Swipeable gallery

**Product Details Panel**:
- Product title (H1)
- Category breadcrumb
- Price (large, prominent)
- Discount badge
- Star rating + review count
- Short description
- Stock status indicator
- Quantity selector (- / number / +)
- Add to Cart button (primary, large)
- Buy Now button (secondary)
- Wishlist button
- Share buttons
- Delivery info icons

**Product Info Tabs**:
- Description tab
- Specifications table
- Shipping & Returns info

**Related Products**:
- "You May Also Like" carousel
- 4-6 products

**Reviews Section**:
- Overall rating summary
- Rating breakdown bars
- Review list (avatar, name, date, rating, comment)
- Write Review button

---

#### 1.4 User Registration Pages

| Page | Purpose | Components |
|------|---------|------------|
| Rider Signup | Passenger registration | Name, email, phone, password, referral code |
| Driver Application | Driver signup | Full form with vehicle info, documents |
| Driver Status | Check application status | Request ID lookup, status display |

---

#### 1.5 Information Pages

| Page | Purpose |
|------|---------|
| Safety | Safety tips and guidelines |
| Privacy Policy | Legal privacy information |
| Terms of Service | User terms and conditions |
| Contact Us | Contact form with map |
| Service Areas | Coverage map and locations |
| Compliance | Regulatory information |

---

### SECTION 2: COMPANY PORTAL (Fleet/Trucking Dashboard)

**Access**: Companies/Fleet owners (authenticated users)

#### 2.1 Company Dashboard Home - PRIORITY: HIGH

**Layout**: Sidebar navigation (260px) + Main content area

**Sidebar Components**:
- Company logo/name at top
- Navigation menu:
  - Dashboard (home icon)
  - My Bids (gavel icon)
  - Goods/Shipments (truck icon)
  - Shop (cart icon)
  - Hubs (building icon)
  - Routes (map icon)
  - Profile (user icon)
  - Notifications (bell icon)
  - Settings (gear icon)
- Collapse toggle button
- User avatar + name at bottom

**Main Content Area**:
- Welcome banner (video/image slider or gradient)
- Stats cards grid (2 rows × 4 cols)
- Quick actions panel
- Recent activity tables

**Stats Cards Row 1**:
| Card | Icon | Color | Data |
|------|------|-------|------|
| Active Bids | Gavel | Orange (#F97316) | Count |
| Won Bids | Trophy | Green (#22C55E) | Count |
| Active Shipments | Truck | Blue (#3B82F6) | Count |
| Completed | Check-circle | Teal (#14B8A6) | Count |

**Stats Cards Row 2**:
| Card | Icon | Color | Data |
|------|------|-------|------|
| Shop Orders | Shopping-bag | Purple (#8B5CF6) | Count |
| Awaiting Approval | Clock | Rose (#F43F5E) | Count |
| Total Revenue | Currency-naira | Amber (#F59E0B) | Amount |
| Company Rating | Star | Indigo (#6366F1) | Stars |

**Quick Actions Panel**:
- Browse Shop button
- Manage Goods button
- View Bids button
- Manage Hubs button
- My Routes button

**Recent Bids Table**:
- Columns: Request #, Route, Amount, Status, Date, Actions
- Status badges (Pending/Accepted/Rejected)
- View details link

**Recent Shipments Table**:
- Columns: Request #, Origin → Destination, Status, Date, Actions
- Status badges (Pending/Picked Up/In Transit/Delivered)
- Track button

**Recent Shop Orders Table**:
- Columns: Order #, Items, Total, Payment, Status, Date, Actions
- Payment badges (Paid/Pending/Failed)
- View details button

---

#### 2.2 Bids Management - PRIORITY: HIGH

**Available Bids Page**:
- Header with page title
- Filter bar (route, date, type)
- Bid cards/list
- Each card shows: Route, Cargo type, Weight, Deadline, Starting bid
- "Place Bid" button

**Create Bid Page**:
- Route selection (dropdown with search)
- Proposed amount input
- Vehicle type selection (truck icons)
- Delivery timeline
- Additional notes textarea
- Submit button

**Bid History Page**:
- Table: Bid #, Route, Amount, Status, Submitted, Updated
- Status filters: All, Pending, Accepted, Rejected, Withdrawn
- Pagination

**Bid Detail Page**:
- Full bid information
- Status timeline
- Related shipment info (if accepted)

---

#### 2.3 Goods/Shipments Management - PRIORITY: HIGH

**Goods List Page**:
- Tabs: All, Pending, In Transit, Delivered
- Table with columns: ID, Origin, Destination, Status, Driver, Date
- Search + filters

**Pending Goods Page**:
- List of goods awaiting pickup
- Accept/Decline actions
- Assign driver option

**Goods Pricing Page**:
- Route-based pricing table
- Add/Edit price rules
- Bulk pricing upload

**Goods Detail Page**:
- Full shipment information
- Sender/Receiver details
- Package details (weight, dimensions)
- Status timeline with updates
- Map showing route
- Document attachments
- Action buttons (Update Status, Assign Driver, Add Fee)

---

#### 2.4 Company Shop - PRIORITY: MEDIUM

**Shop Catalog**:
- Similar to public shop but with company pricing
- Category sidebar
- Product grid with bulk add options

**Cart Page**:
- Item list with quantity adjusters
- Remove item button
- Subtotal per item
- Order summary sidebar
- Apply coupon code
- Proceed to Checkout

**Checkout Page**:
- Shipping address form
- Delivery method selection (standard/express)
- Payment method selection
- Order summary
- Place Order button

**Orders List**:
- Table: Order #, Date, Items, Total, Status, Actions
- Status: Processing, Shipped, Delivered, Cancelled

**Order Detail**:
- Full order information
- Items list with images
- Shipping tracking
- Invoice download button

---

#### 2.5 Hubs Management - PRIORITY: MEDIUM

**Hubs List Page**:
- Table: Hub Name, Location, Capacity, Status, Actions
- Add New Hub button
- Edit/Delete actions

**Add/Edit Hub Modal**:
- Hub name input
- Location search (Google Maps)
- Address fields
- Capacity input
- Contact person
- Phone/Email
- Save/Cancel buttons

---

#### 2.6 Routes Management - PRIORITY: MEDIUM

**Routes List Page**:
- Table: Route Name, Origin, Destination, Distance, Duration, Status
- Add/Edit/Delete actions

**Route Detail Page**:
- Route map visualization
- Waypoints list
- Estimated time/distance
- Pricing tiers

---

#### 2.7 Profile Settings - PRIORITY: MEDIUM

**Profile Edit Page**:
- Company logo upload
- Company name
- Email, Phone
- Address
- Business type
- Documents upload (CAC, insurance, etc.)
- Save button

**Change Password Page**:
- Current password
- New password
- Confirm password

**Notification Preferences**:
- Email notifications toggle
- SMS notifications toggle
- Push notifications toggle
- Notification type checkboxes

---

#### 2.8 Notifications - PRIORITY: LOW

**Notifications List**:
- List view with icons
- Unread indicator (dot)
- Timestamp
- Mark as read action
- Delete action
- Filter by type

---

### SECTION 3: DISPATCH DASHBOARD

**Access**: Dispatch operators (authenticated)

#### 3.1 Dispatch Home - PRIORITY: HIGH

**Layout**: Full-screen map + Collapsible sidebar (right side)

**Header Bar**:
- Logo (left)
- Quick action buttons: Book Now, Book Later, Delivery
- Search requests
- Notifications bell
- User avatar + dropdown

**Map Area** (85% of screen):
- Google Maps or Mapbox
- Driver markers with status colors:
  - Available: Green
  - On Trip: Blue
  - Offline: Gray
- Request markers (pulsing)
- Cluster markers for density
- Map controls (zoom, fullscreen, layers)
- Legend overlay

**Request Sidebar** (right, 350px width):
- Toggle button to show/hide
- Tab filters: All, Searching, Driver Assigned, In Progress, Completed
- Request cards list:
  - Request ID
  - Type icon (ride/delivery)
  - Pickup → Dropoff
  - Status badge
  - Time elapsed
  - Assign/View button
- Pagination or infinite scroll

**Driver Info Popup** (on marker click):
- Driver photo
- Name
- Vehicle type
- Rating
- Current status
- Phone call button

---

#### 3.2 Book Now Modal - PRIORITY: HIGH

**Form Fields**:
- Pickup location input (autocomplete)
- Drop-off location input (autocomplete)
- Recent locations dropdown
- Vehicle type selection (cards with icons):
  - Car (sedan, SUV)
  - Bike
  - Truck (for delivery)
- Estimated price display
- Estimated time
- Estimated distance
- Notes for driver (optional)
- Confirm booking button

**Design**:
- Centered modal (max-width: 500px)
- Map showing route preview
- Price calculator in real-time

---

#### 3.3 Book Later Modal - PRIORITY: MEDIUM

**Additional Fields**:
- Date picker
- Time picker
- Schedule summary

---

#### 3.4 Request Detail Page - PRIORITY: HIGH

**Layout**: Full page with map + details panel

**Sections**:
- Request ID header
- Status timeline (vertical)
- Customer info card
- Driver info card (if assigned)
- Trip details (pickup, dropoff, route)
- Vehicle info
- Payment info
- Action buttons:
  - Assign Driver
  - Cancel Request
  - Mark Complete
  - View on Map

---

#### 3.5 Request List View - PRIORITY: MEDIUM

**Layout**: Full table view替代map view

**Features**:
- Search by request ID, customer, driver
- Filter by status, date range, type
- Sortable columns
- Export to CSV/PDF
- Bulk actions

---

#### 3.6 Ongoing Trips - PRIORITY: MEDIUM

**Layout**: Table + Real-time updates

**Columns**:
- Request #
- Customer
- Driver
- Pickup → Dropoff
- Status
- Time elapsed
- Actions

---

### SECTION 4: ADMIN PANEL (Overview)

**Note**: This is extensive (30+ modules). Focus on key areas for redesign:

| Module | Description |
|--------|-------------|
| Dashboard | Platform statistics, charts |
| User Management | Users, drivers, companies |
| Fleet Management | Vehicles, types, zones |
| Zone Management | Service areas, pricing zones |
| Promotions | Coupons, offers, referrals |
| Reports | Analytics, exports |
| Settings | Global configuration |

---

## 🧩 COMPONENT LIBRARY

### Buttons

| Type | Variants | States |
|------|----------|--------|
| Primary | Filled, Outline | Default, Hover, Active, Disabled, Loading |
| Secondary | Filled, Outline | Default, Hover, Active, Disabled |
| Icon Button | Circle, Square | Default, Hover, Active |
| Dropdown | Single select, Multi-select | Default, Open, Disabled |
| Button Group | Horizontal, Vertical | - |

### Cards

| Type | Use Case |
|------|----------|
| Stat Card | Dashboard metrics (icon, number, label, trend) |
| Product Card | Shop items (image, badges, info, actions) |
| Request Card | Dispatch requests |
| Profile Card | User/driver info |
| Notification Card | Notification items |

### Forms

| Component | Variants |
|-----------|----------|
| Text Input | Default, With icon, With validation |
| Select Dropdown | Single, Multi, Searchable |
| Date Picker | Single date, Range |
| Time Picker | 12h/24h format |
| Toggle Switch | On/Off with label |
| Checkbox | Single, Group, Indeterminate |
| Radio Button | Group selection |
| File Upload | Single, Multiple, Drag & drop |
| Textarea | Auto-expand, Character count |
| Search Input | With icon, Autocomplete |

### Tables

| Feature | Description |
|---------|-------------|
| Basic Table | Standard rows/columns |
| Sortable | Click header to sort |
| Filterable | Filter row or dropdown filters |
| Pagination | Numbered, Previous/Next |
| Row Selection | Checkbox selection |
| Row Actions | Edit, Delete, View |
| Expandable Rows | Show more details |
| Empty State | "No data" illustration |

### Navigation

| Component | Variants |
|-----------|----------|
| Top Navbar | Fixed, Sticky, Static |
| Sidebar | Collapsible, Icon-only, Full |
| Breadcrumbs | With icons, Without icons |
| Tabs | Horizontal, Vertical, Pill |
| Pagination | Numbered, Load more |
| Drawer/Menu | Mobile navigation |

### Modals

| Type | Use Case |
|------|----------|
| Standard Modal | Centered, with header/footer |
| Full-screen Modal | For complex forms |
| Side Panel | Slide-in from right |
| Confirmation Dialog | Delete confirmations |
| Bottom Sheet | Mobile modals |

### Maps

| Component | Description |
|-----------|-------------|
| Map Markers | Vehicle types, status colors |
| Info Windows | Driver/request details |
| Map Controls | Zoom, layers, fullscreen |
| Route Polyline | Pickup to dropoff line |
| Geofence Zones | Service area boundaries |

### Feedback

| Component | Use Case |
|-----------|----------|
| Toast Notification | Success, Error, Warning, Info |
| Alert Banner | Top/bottom dismissible |
| Loading Spinner | Button, Page, Component |
| Progress Bar | Determinate, Indeterminate |
| Skeleton | Content loading placeholder |
| Empty State | No results illustrations |

---

## ✨ ANIMATION & INTERACTIONS

### Transitions

- **Duration**: 150ms (micro), 250ms (standard), 400ms (emphasis)
- **Easing**: ease-out for entering, ease-in for exiting
- **Properties**: transform, opacity, color, shadows

### Interactions

| Element | Interaction |
|---------|-------------|
| Buttons | Scale down on press (0.98), color shift on hover |
| Cards | Lift on hover (translateY -4px), shadow increase |
| Links | Underline slide-in |
| Images | Zoom on hover (scale 1.05) |
| Sidebar | Slide in/out with content push |
| Modals | Fade + scale in |
| Dropdowns | Fade + slide down |
| Notifications | Slide in from top-right, auto-dismiss |
| Loading | Skeleton shimmer effect |

### Page Transitions

- Route changes: Fade
- Section scroll: Fade-in-up
- Tab switches: Cross-fade

---

## 📐 RESPONSIVE BREAKPOINTS

| Breakpoint | Width | Layout |
|------------|-------|--------|
| xs | < 576px | Single column, stacked |
| sm | 576px - 767px | 2 columns, condensed |
| md | 768px - 991px | 3 columns, sidebar collapse |
| lg | 992px - 1199px | 4 columns, full sidebar |
| xl | 1200px - 1399px | Full layout, expanded |
| xxl | ≥ 1400px | Max content width 1400px |

### Mobile-Specific Considerations

- Bottom navigation bar for main actions
- Swipeable galleries
- Pull-to-refresh
- Large touch targets (min 44px)
- Simplified forms
- Collapsible sections

---

## 📦 DELIVERABLES FROM FIGMA AI

### 1. Design System
- [ ] Color palette with all defined colors
- [ ] Typography scale (all font sizes, weights)
- [ ] Spacing system
- [ ] Border radius scale
- [ ] Shadow definitions
- [ ] Icon library suggestions

### 2. Component Library
- [ ] All buttons (states, variants)
- [ ] All cards (stat, product, request, profile)
- [ ] Form components (all types)
- [ ] Tables (all features)
- [ ] Navigation (navbars, sidebars, tabs)
- [ ] Modals (all types)
- [ ] Map components
- [ ] Feedback (toasts, alerts, loading, empty states)

### 3. Page Designs
- [ ] Landing Page (all sections)
- [ ] Shop Page (with all states)
- [ ] Product Detail Page
- [ ] Company Dashboard (all sections)
- [ ] Bids Management (all pages)
- [ ] Goods Management (all pages)
- [ ] Company Shop (all pages)
- [ ] Dispatch Dashboard (all pages)
- [ ] Registration/Info pages

### 4. Responsive Variants
- [ ] Mobile layouts (375px)
- [ ] Tablet layouts (768px)
- [ ] Desktop layouts (1440px)

### 5. States & Variations
- [ ] Hover states for all interactive elements
- [ ] Active/pressed states
- [ ] Disabled states
- [ ] Loading states
- [ ] Empty states
- [ ] Error states
- [ ] Success states

---

## 🔑 BRAND GUIDELINES

### Name & Logo

- **Name**: FETCH
- **Logo Gradient**: #2bc9de (teal) → #4F46E5 (indigo)
- **Taglines**: 
  - "Your Ride, Your Way"
  - "Reliable. Affordable. Safe."

### Tone of Voice

- Professional yet friendly
- Clear and concise
- Trustworthy and secure
- Nigerian market appropriate

---

## 🌍 MARKET SPECIFICATIONS

- **Currency**: Nigerian Naira (₦)
- **Language**: English (with i18n support ready)
- **Date Format**: DD/MM/YYYY
- **Time Format**: 12-hour (with AM/PM)
- **Map Provider**: Google Maps / Mapbox
- **Real-time**: Firebase integration

---

## 📝 IMPORTANT NOTES

1. **Real-time Features**: Map shows live driver positions (Firebase)
2. **Video Support**: Hero sections support video backgrounds
3. **Dark Mode**: Design should accommodate dark theme for certain sections (featured products, dispatch map)
4. **Accessibility**: WCAG 2.1 AA compliance (color contrast, keyboard navigation, screen readers)
5. **Performance**: Lightweight designs for fast loading (optimize images, minimal effects)
6. **Nigerian Context**: 
   - Use Naira currency consistently
   - Include Nigerian states/cities in location data
   - Support local payment methods

---

## 🎯 SCREEN PRIORITY FOR REDESIGN

### Phase 1 (High Priority)
1. Landing Page
2. Shop Page
3. Product Detail Page
4. Company Dashboard Home
5. Dispatch Dashboard

### Phase 2 (Medium Priority)
6. Company Bids Management
7. Company Goods Management
8. Company Shop + Checkout
9. Book Now/Book Later Modals
10. Request Detail Page

### Phase 3 (Lower Priority)
11. Registration Pages
12. Profile & Settings
13. Hubs & Routes
14. Info Pages (Privacy, Terms, etc.)

---

*This master prompt should be fed to Figma AI to generate a complete design system and all required screen mockups for the FETCH-MarketSquare platform redesign. The prompt focuses on UI for new features while maintaining consistency with existing functionality.*
