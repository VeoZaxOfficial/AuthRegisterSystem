# AuthRegisterSystem

A simple and lightweight authentication plugin for PocketMine-MP (API 2.0.0)

## ✨ Features

* Register & Login system
* Password hashing (secure)
* Login attempt limiter
* Auto kick on timeout
* Movement & action restriction before authenticate
* Owner-only account removal command

## 📥 Installation

1. Download the plugin `.phar` file
2. Place it in your server's `/plugins/` folder
3. Restart the server

⬇ Download Link: [Click Here To Download](https://www.mediafire.com/file/s2mi1wauh9zua92/AuthRegisterSystem_v0.14.3_0.15.10.phar/file)

## ⚙️ Commands

| Command                         | Description                            |
| ------------------------------- | -------------------------------------- |
| /register <password>            | Register your account                  |
| /login <password>               | Login to your account                  |
| /changepassword <new> <confirm> | Change your password                   |
| /auth remove <player>           | Remove a player's account (owner only) |

## 🔒 Permissions

* Only the **owner** (set in config.yml) can use `/auth`

## 🛠️ Configuration

Edit `config.yml` to customize:

* Messages
* Timeout
* Owner name

## 📌 Notes

* Passwords are securely hashed using PHP password hashing
* Players cannot move or interact until logged in

## 👤 Author

* Developed by VeoZax

## 📜 License

This project is licensed under the MIT License.
