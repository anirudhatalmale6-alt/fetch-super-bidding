# LOGIN URLs

## Super Admin Login

| Type | URL |
|------|-----|
| **Web (Browser)** | `http://your-domain.com/admin/login` |
| **API** | `POST http://your-domain.com/api/v1/admin/login` |

## Company/Fleet Owner Login

| Type | URL |
|------|-----|
| **Web (Browser)** | `http://your-domain.com/company-login` |
| **API** | `POST http://your-domain.com/api/v1/login` |

## User Types in Tagxi

| User Type | Login URL | Purpose |
|-----------|-----------|---------|
| **Super Admin** | `/admin/login` | Full system management |
| **Fleet/Company Owner** | `/company-login` | Manage fleets & drivers (taxi) |
| **Trucking Company** | `/api/v1/login` | Interstate logistics & shop |
| **Driver** | `/api/v1/driver/login` | Accept trips |

## Other Login Endpoints

| Role | URL |
|------|-----|
| User | `POST /api/v1/user/login` |
| Dispatcher | `/dispatch-login` |

## Example API Login

**Admin:**
```http
POST /api/v1/admin/login
{
    "email": "admin@example.com",
    "password": "password"
}
```

**Company/Fleet Owner:**
```http
POST /api/v1/login
{
    "email": "company@example.com",
    "password": "password"
}
```

## Note

**Fleet Owner** = **Company Owner** (same user type in Tagxi)
- Manages vehicle fleets
- Hires drivers
- Views: `FleetOwnerController@dashboard`

**Trucking Company** (interstate logistics)
- Different from Fleet Owner
- Manages interstate shipping
- Uses shop for purchases
