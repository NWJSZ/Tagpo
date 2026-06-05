# Tagpo — Event Booking and Management Platform

## Overview

Tagpo is a web-based Event Booking and Management Platform designed to simplify the process of planning, organizing, and managing events. The platform provides users with a centralized system for browsing available venues, making reservations, selecting event-related services, and managing bookings efficiently.

Built using PHP and MySQL, Tagpo follows a client-server architecture where users interact through a web interface while server-side scripts handle business logic, data processing, and database communication. The system aims to provide a convenient, organized, and user-friendly experience for both customers and administrators.

---

## Key Features

### User Management

* User registration and authentication
* Secure login and logout functionality
* Role-based access control (Administrator and Standard User)
* Session and cookie management for user authentication

### Venue Management

* Browse available event venues
* View venue details, descriptions, images, pricing, and availability
* Administrator controls for adding, updating, and removing venues
* Venue availability monitoring

### Booking Management

* Create and manage event reservations
* Select preferred venue, event date, and booking details
* Store guest count, event duration, and event type information
* Reservation status tracking (Pending, Approved, Rejected, Completed)
* Booking history and record management

### Add-ons and Event Services

* Browse available event services and add-ons
* Select optional services during reservation
* Customize bookings based on event requirements
* Associate selected services with reservations

### Payment Management

* Track booking payments
* Store total reservation costs
* Maintain payment records linked to bookings
* Support reservation confirmation based on payment status

---

## System Architecture

Tagpo utilizes a client-server architecture consisting of:

### Front-End

* HTML
* CSS
* JavaScript

### Back-End

* PHP

### Database

* MySQL Relational Database

### Session Management

* PHP Sessions
* Browser Cookies

The front-end interface allows users to interact with the system while PHP processes requests, validates user input, performs business logic, and communicates with the MySQL database.

---

## Database Modules

### A. User Management

Stores user account information and authentication credentials.

**Sample Data:**

* User ID
* Full Name
* Username
* Email Address
* Password
* User Role

### B. Venue Management

Stores venue information and availability records.

**Sample Data:**

* Venue ID
* Venue Name
* Description
* Price
* Capacity
* Availability Status
* Venue Images

### C. Booking Management

Stores reservation and event details.

**Sample Data:**

* Booking ID
* Customer Information
* Event Type
* Selected Venue
* Booking Date
* Event Date
* Event Duration
* Number of Guests
* Total Amount
* Booking Status

### D. Add-ons and Event Services

Stores available services and booking customizations.

**Sample Data:**

* Service ID
* Service Name
* Service Description
* Service Price
* Associated Booking

### E. Payment Management

Stores payment records connected to reservations.

**Sample Data:**

* Payment ID
* Booking ID
* Payment Amount
* Payment Method
* Payment Status
* Transaction Date

---

## Objectives

The primary objectives of Tagpo are:

* Simplify event reservation and management processes
* Provide real-time booking and availability monitoring
* Maintain accurate reservation records
* Improve accessibility for users through a web-based platform
* Support administrators in managing venues, bookings, and event services efficiently
* Ensure secure handling of user and reservation information

---

## Technologies Used

* PHP
* MySQL
* HTML5
* CSS3
* JavaScript
* PHP Sessions
* Cookies

---

## Future Enhancements

Potential future improvements include:

* Online payment gateway integration
* Automated email notifications
* Event calendar synchronization
* Customer reviews and ratings
* Analytics and reporting dashboard
* Mobile-responsive enhancements
* Multi-venue scheduling and advanced booking management
