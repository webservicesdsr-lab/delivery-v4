Prompt para armar tree: tree -a -I "node_modules|vendor|.git" > project-tree.txt

***KINGDOM NEXUS** is a system built using development practices commonly found in modern frameworks like Laravel 9–11—focusing on modularity, security, organization, controllers, roles, and clean separation of logic.
From the beginning, the goal has been clear:

👉 **Create a stable, secure, PHP-driven platform where collaborators can contribute without needing full full-stack expertise.**

---

## **1. Technical Environment & Justification**

To avoid the complexity of managing servers, DevOps, networking, certificates, deployments, or an unmanaged VPS, a strategic decision was made:

👉 **Use HostGator Business Plan as the core infrastructure.**

This provided:

* A fully managed environment
* Security and support without needing a senior DevOps engineer
* One-click installation of Laravel or WordPress through **Softaculous**
* Automatic handling of server-level concerns (Apache/Nginx, firewalls, cron, SSL, etc.)

Softaculous created ready-to-use WordPress or Laravel environments instantly, allowing development to begin without manual configuration.

---

## **2. WordPress as a Container**

Although WordPress is traditionally a PHP CMS, it serves an essential role in this project:

* Instant setup
* Built-in admin panel
* Preconfigured sessions and permissions
* Direct, secure access to phpMyAdmin for custom database tables
* A file manager for full code control
* A stable, familiar environment for collaborators

Inside this setup, **Nexus lives as a custom mini-plugin**—but in reality, that plugin hides an entire framework dedicated to delivery operations.

WordPress acts purely as the container.
Nexus handles the actual business logic.

---

## **3. KINGDOM NEXUS Internal Architecture**

This is where the project evolved from experimentation into a real structured system.

Inside the plugin:

* A custom loader works as an internal router
* Modules behave like standalone application components
* A full modular structure exists (`/core/`, `/modules/`, `/public/`)
* Security patterns are inspired by Laravel, adapted to WordPress equivalents
* A custom user/session/role system was built
* APIs exist entirely outside the native WP REST API
* The database is fully custom, using MySQL tables designed for real relationships

**WordPress provides**:
server security, admin UI, file management, autoload basics, PHP integration, and a ready production environment.

**Nexus provides**:

* Its own APIs
* Delivery zones, hours, temporary closures
* Modifiers, addons, and advanced menu logic
* Cart, checkout, and secure backend validation
* A complete order system
* A modular architecture comparable to a lightweight framework
* Full control to extend or modify the system

---

## **4. Database Design**

Before learning structured MySQL design, the project grew through experimentation.
Once relational concepts became clear, the system adopted proper database modeling:

* Primary keys
* Entity relationships
* Normalization
* Many-to-many bridge tables
* Snapshots to prevent inconsistent data
* A complete ERD for visualization and planning

At this point, Nexus stopped depending on WordPress behavior and began behaving like a standalone framework with professional data architecture.

---

# -------------------------------------------

# **===== > KNX 1**

# -------------------------------------------
