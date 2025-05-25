# TaskBuddy 🛠️

A web‑based platform that connects clients with skilled taskers in their area. Book services, chat with providers, and manage task requests all in one place.


## 📝 Overview

- **What it does**  
  Connects people who need tasks done with local professionals.  
- **Who it’s for**  
  - **Clients**: Book, track, and review services  
  - **Taskers**: Offer services, manage requests, showcase portfolios  


## ⭐ Features

### For Clients 👨‍💼👩‍💼

- **Browse Services**  
  Search skilled taskers by category  
- **Book Appointments**  
  Pick date & time slots that work for you  
- **Task Tracking**  
  See if your booking is pending, accepted, completed, or cancelled  
- **Reviews & Ratings**  
  Leave feedback once a task is done  
- **Messaging System**  
  Real‑time chat (WebSocket‑powered via Ratchet)  


### For Taskers 🔧👷‍♀️

- **Service Management**  
  Add or edit your service offerings  
- **Request Handling**  
  Accept, decline, or mark bookings as completed  
- **Portfolio Showcase**  
  Upload project photos to attract clients  
- **Real‑time Notifications**  
  Instant alerts for new requests  
- **Income Tracking**  
  Monitor completed tasks and earnings  
- **Messaging Center**  
  Chat with clients via the integrated system  


### For Admins 🛡️

- **Dashboard Overview**  
  See key stats: users, bookings, revenue, top-rated taskers  
- **Category Management**  
  Add, edit, and delete service categories with visual cards  
- **User Management**  
  Search/filter users, promote/demote admins, delete accounts  
- **Booking Management**  
  Filter bookings by status/date, update statuses, export to CSV  
- **Analytics & Reports**  
  Visual charts for booking trends and category distribution  


## 💻 Technologies Used

- **Backend:** PHP 7.4+  
- **Database:** MySQL 5.7+  
- **Frontend:** HTML5, CSS3, JavaScript, Bootstrap 5  
- **Real‑time Communication:** Ratchet PHP WebSocket library  
- **Email Notifications:** PHPMailer  
- **Authentication:** Custom PHP session‑based system  



## 🚀 Usage

### Client

1. Sign up or log in  
2. Browse services and filter by category  
3. View profiles, reviews, portfolios  
4. Book a tasker with task details  
5. Chat with your tasker  
6. Track booking status  
7. Leave a review  


### Tasker

1. Register as a tasker  
2. Set up your profile (skills, rates, portfolio)  
3. Receive and manage booking requests  
4. Communicate with clients  
5. Mark tasks as completed  
6. Build reputation via reviews  


## 🔒 Security

- Input sanitization against SQL injection  
- Session‑based auth  
- CSRF tokens on all forms  


## 📄 License

Licensed under the MIT License.
