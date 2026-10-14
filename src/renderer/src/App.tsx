import { useState } from 'react'
import {
  Activity, ArrowDownRight, ArrowUpRight, BarChart3, Bell, ChevronDown,
  CircleHelp, ClipboardList, CreditCard, LayoutDashboard, Package,
  Plus, Search, Settings, ShoppingBag, ShoppingCart, Store, Users,
  Wallet, Utensils, Truck, ReceiptText, Boxes, Coffee,
} from 'lucide-react'

type NavItem = { label: string; icon: typeof LayoutDashboard; group: string }

const navItems: NavItem[] = [
  { label: 'Dashboard', icon: LayoutDashboard, group: 'WORKSPACE' },
  { label: 'Point of Sale', icon: ShoppingCart, group: 'WORKSPACE' },
  { label: 'Sales & Invoices', icon: ReceiptText, group: 'WORKSPACE' },
  { label: 'Products', icon: Package, group: 'CATALOG' },
  { label: 'Inventory', icon: Boxes, group: 'CATALOG' },
  { label: 'Purchases', icon: Truck, group: 'CATALOG' },
  { label: 'Customers', icon: Users, group: 'RELATIONSHIPS' },
  { label: 'Suppliers', icon: Store, group: 'RELATIONSHIPS' },
  { label: 'Expenses', icon: Wallet, group: 'FINANCE' },
  { label: 'Reports & Analytics', icon: BarChart3, group: 'FINANCE' },
  { label: 'Restaurant', icon: Utensils, group: 'OPERATIONS' },
  { label: 'Settings', icon: Settings, group: 'SYSTEM' },
]

const stats = [
  { label: "Today's Revenue", value: 'Rs. 0', change: 'No sales recorded yet', icon: CreditCard, tone: 'blue' },
  { label: 'Orders Today', value: '0', change: 'Ready for your first sale', icon: ShoppingBag, tone: 'violet' },
  { label: 'Gross Profit', value: 'Rs. 0', change: 'Calculated from completed sales', icon: Activity, tone: 'green' },
  { label: 'Low Stock Items', value: '0', change: 'All stock levels look good', icon: Package, tone: 'amber' },
]

function App() {
  const [active, setActive] = useState('Dashboard')
  const [search, setSearch] = useState('')

  const groups = [...new Set(navItems.map((item) => item.group))]

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <div className="brand">
          <div className="brand-mark"><Store size={21} strokeWidth={2.3} /></div>
          <div><strong>abyte<span>POS</span></strong><small>RETAIL & RESTAURANT</small></div>
        </div>

        <button className="business-switcher">
          <span className="business-icon"><Coffee size={17} /></span>
          <span className="business-copy"><strong>My Business</strong><small>Universal workspace</small></span>
          <ChevronDown size={16} className="muted-icon" />
        </button>

        <nav className="navigation">
          {groups.map((group) => (
            <div className="nav-group" key={group}>
              <p className="nav-heading">{group}</p>
              {navItems.filter((item) => item.group === group).map((item) => {
                const Icon = item.icon
                return (
                  <button
                    key={item.label}
                    className={`nav-item ${active === item.label ? 'active' : ''}`}
                    onClick={() => setActive(item.label)}
                  >
                    <Icon size={18} strokeWidth={1.9} />
                    <span>{item.label}</span>
                    {item.label === 'Inventory' && <span className="nav-count">0</span>}
                  </button>
                )
              })}
            </div>
          ))}
        </nav>

        <div className="sidebar-footer">
          <div className="offline-indicator"><span /> Offline mode ready</div>
          <div className="version-label">ABYTE SIMPLE POS <span>v0.1.0</span></div>
        </div>
      </aside>

      <main className="main-panel">
        <header className="topbar">
          <div className="breadcrumbs"><span>Workspace</span><span className="crumb-slash">/</span><strong>{active}</strong></div>
          <div className="topbar-actions">
            <label className="search-box">
              <Search size={17} />
              <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search anything..." />
              <kbd>⌘ K</kbd>
            </label>
            <button className="icon-button" aria-label="Help"><CircleHelp size={19} /></button>
            <button className="icon-button notification-button" aria-label="Notifications"><Bell size={19} /><i /></button>
            <div className="profile">
              <div className="avatar">A</div>
              <div className="profile-copy"><strong>Administrator</strong><small>Store owner</small></div>
              <ChevronDown size={15} />
            </div>
          </div>
        </header>

        <div className="page-content">
          <div className="welcome-row">
            <div>
              <div className="eyebrow"><span className="eyebrow-dot" /> BUSINESS OVERVIEW</div>
              <h1>{active === 'Dashboard' ? 'Good evening, Admin' : active}</h1>
              <p className="page-subtitle">{active === 'Dashboard' ? 'Here’s what’s happening with your business today.' : `Your ${active.toLowerCase()} workspace. Module setup is coming in the next development phase.`}</p>
            </div>
            <button className="primary-button" onClick={() => setActive('Point of Sale')}><Plus size={18} /> New sale</button>
          </div>

          <section className="stats-grid">
            {stats.map((stat, index) => {
              const Icon = stat.icon
              return (
                <article className="stat-card" key={stat.label}>
                  <div className="stat-top"><span>{stat.label}</span><div className={`stat-icon ${stat.tone}`}><Icon size={19} /></div></div>
                  <div className="stat-value">{stat.value}</div>
                  <div className="stat-foot">{index === 0 ? <ArrowUpRight size={15} /> : index === 3 ? <ArrowDownRight size={15} /> : <Activity size={14} />}<span>{stat.change}</span></div>
                </article>
              )
            })}
          </section>

          <section className="content-grid">
            <article className="panel sales-panel">
              <div className="panel-heading">
                <div><h2>Sales overview</h2><p>Your revenue performance at a glance</p></div>
                <button className="period-select">Today <ChevronDown size={15} /></button>
              </div>
              <div className="chart-empty">
                <div className="chart-illustration"><BarChart3 size={30} /></div>
                <strong>Your sales story starts here</strong>
                <span>Complete your first sale to see business trends.</span>
                <button className="text-button" onClick={() => setActive('Point of Sale')}>Go to Point of Sale <ArrowUpRight size={15} /></button>
              </div>
            </article>

            <article className="panel quick-panel">
              <div className="panel-heading"><div><h2>Quick actions</h2><p>Get things done faster</p></div></div>
              <div className="quick-actions">
                <button onClick={() => setActive('Point of Sale')}><span className="quick-icon blue"><ShoppingCart size={19} /></span><span><strong>New sale</strong><small>Start a checkout</small></span><Plus size={16} className="quick-plus" /></button>
                <button onClick={() => setActive('Products')}><span className="quick-icon violet"><Package size={19} /></span><span><strong>Add product</strong><small>Build your catalog</small></span><Plus size={16} className="quick-plus" /></button>
                <button onClick={() => setActive('Customers')}><span className="quick-icon green"><Users size={19} /></span><span><strong>Add customer</strong><small>Grow your customer list</small></span><Plus size={16} className="quick-plus" /></button>
                <button onClick={() => setActive('Expenses')}><span className="quick-icon amber"><Wallet size={19} /></span><span><strong>Record expense</strong><small>Track business spending</small></span><Plus size={16} className="quick-plus" /></button>
              </div>
            </article>
          </section>

          <section className="panel transactions-panel">
            <div className="panel-heading">
              <div><h2>Recent transactions</h2><p>Your latest sales and payment activity</p></div>
              <button className="secondary-button" onClick={() => setActive('Sales & Invoices')}>View all <ArrowUpRight size={15} /></button>
            </div>
            <div className="empty-table">
              <div className="empty-table-icon"><ClipboardList size={23} /></div>
              <strong>No transactions yet</strong>
              <span>Once you make a sale, it will appear here.</span>
            </div>
          </section>

          <footer className="page-footer"><span>© 2026 AbyteSol. Built for your business.</span><span><i /> Local workspace <span className="footer-divider">·</span> Data stays on this device</span></footer>
        </div>
      </main>
    </div>
  )
}

export default App
