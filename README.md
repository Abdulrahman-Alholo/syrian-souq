# 🛒 Syrian-Souq (Multi-Vendor Marketplace)

> A modern, robust, and scalable multi-vendor e-commerce platform architected as a **Tech Enabler / Aggregator** for local merchants and brands.

---

## 🏗️ Architectural Highlights

The platform is designed following **Domain-Driven Design (DDD)** and **Modular Monolith** principles to ensure high maintainability, strict logical isolation, and data integrity:

* **Modular Architecture:** Business domains are strictly decoupled under `app/Modules/` (`Order`, `Finance`, `Catalog`, `Vendor`, `Cart`, `Payment`, `User`).
* **Order Splitting Engine:** Automatically breaks down a unified customer cart into dedicated `VendorOrder` child records via background jobs, guarded by database composite unique constraints (`order_id`, `vendor_id`) to prevent race conditions.
* **Strict State Machine:** Order transitions are enforced directly within the Eloquent models with custom backend guards, isolating vendor capabilities from platform admins.
* **Double-Entry Style Financial Ledger:** An append-only, immutable transaction ledger (`LedgerService`) utilizing pessimistic locking (`lockForUpdate`) and strict idempotency keys (`idempotency_key`) to guarantee exact balance calculations and prevent double-payout exploits.
* **Dual Filament Admin Panels:** Independent panels for Platform Super-Admins and scoped Vendor dashboards, built with read-only hydration patterns for parent relationship data.

---

## 🛠️ Tech Stack

* **Backend Framework:** Laravel 11 / PHP 8.2+
* **Admin & Vendor Panels:** Filament v3 (Livewire 3 & Alpine.js)
* **Database:** MySQL (Single-database architecture with scoped logical isolation)
* **Queues & Jobs:** Database / Sync driver

---

## 🚀 Key Modules Breakdown

```text
app/Modules/
├── Catalog/   # Products, Variants, Inventory
├── Cart/      # Multi-vendor cart management
├── Order/     # Orders, VendorOrders, Splitting Engine, Workflow Observers
├── Finance/   # LedgerService, Immutable Transactions, Payout Lifecycle
├── Payment/   # Payment gateway interfaces (COD / Online)
├── Vendor/    # Store configuration, commission policies
└── User/      # Authentication, Customer profiles
