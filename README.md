# Lost and Found System

## Project Overview

The Lost and Found System is a web-based application developed for CBE College to help students and staff report, search for, and recover lost and found items through a centralized online platform.

The system provides an organized alternative to relying on physical notice boards, word of mouth, or manually searching for lost property.

## Problem Statement

Students and staff can easily lose personal belongings within the college environment. Traditional methods of reporting and finding lost items can be slow and make it difficult for people to connect with the person who found their property.

This system provides a centralized platform where users can report lost items, report found items, search available reports, and submit claims for items they believe belong to them.

## Main Features

* User registration and login
* User authentication and logout
* Student dashboard
* Admin dashboard
* Report lost items
* Report found items
* Search for lost and found items
* View reported items
* Submit claims for found items
* Manage users
* Manage reported items
* Manage claims
* Change user password
* Database-driven item management

## Technologies Used

* **PHP** — Backend/server-side development
* **MySQL** — Database management
* **HTML** — Web page structure
* **CSS** — User interface styling
* **JavaScript** — Client-side functionality
* **XAMPP** — Local development environment
* **Git & GitHub** — Version control and project collaboration

## System Structure

The project contains separate sections for authentication, student functionality, administration, and database configuration.

```text
lost_found/
├── admin/
├── auth/
├── config/
├── student/
├── index.php
├── lost_found_db.sql
└── README.md
```

## My Contribution

This project was developed as a group project with two other group members.

My personal contribution included working on the user authentication system, connecting the application to the MySQL database, developing PHP backend functionality, integrating frontend pages with backend functionality, and testing and debugging different parts of the system.

I also contributed to the overall development and testing of the Lost and Found System to ensure that users could report, search for, and manage lost and found items successfully.

## Database

The system uses MySQL to store information related to users, lost and found items, claims, and other system data.

The `lost_found_db.sql` file is included in this repository to provide the database structure required to run the application.

## Running the Project Locally

### Requirements

* XAMPP
* Apache
* MySQL
* PHP
* Web browser

### Installation

1. Install and start **XAMPP**.
2. Start **Apache** and **MySQL**.
3. Copy the project folder into the XAMPP `htdocs` directory.

```text
C:\xampp\htdocs\lost_found
```

4. Open **phpMyAdmin**.
5. Create the required database.
6. Import:

```text
lost_found_db.sql
```

7. Check the database configuration in:

```text
config/db.php
```

8. Open the application in your browser through localhost.

Example:

```text
http://localhost/lost_found/
```

## Project Purpose

The main purpose of the project is to provide CBE College with a simple and organized digital platform for managing lost and found property and improving communication between students, staff, and administrators.

## Future Improvements

Possible future improvements include:

* Email notifications
* Image upload and item photo management
* Advanced search and filtering
* Mobile application integration
* Improved security and authentication
* Online notifications for claim updates
* Deployment to a live web server

## Academic Project

This project was developed as part of our academic work in Information Technology at CBE College.

## Authors

Developed by a team of three students as a web-based Lost and Found System project for CBE College.
