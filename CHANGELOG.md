# Changelog

All notable changes to `sanjayacloud/laravel-plans` will be documented in this file.

## 0.5.0 - 2026-10-30

### Added

- `Plan` model with prices, features, limits, Stripe price IDs, default free and trial plan flags
- `HasPlans` trait: `currentPlan()`, `planStatus()`, `hasFeature()`, `planLimit()`, `onPlanTrial()`, `startPlanTrial()`, `wherePlanStatus()` scope
- `PlanResolver` (subscription, then admin grant, then trial, then default free plan)
- `plan.feature` middleware with redirect for browsers and 402 for APIs
- Pricing page with Stripe Checkout and billing portal (when Laravel Cashier is installed)
- Admin area: overview, plans CRUD, users list and detail, trials, complimentary plans, subscription cancel and resume, super admin access, audit log
- Trial on registration listener and `<x-plans::banner />` component
- `plans:grant-admin` and `plans:start-trials` commands
