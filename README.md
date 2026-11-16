1. Cek dulu udah ada node js belom, caranya buka terminal ketik node -v kalau teks merah muncul belum ada berarti, download dulu di google next next aja instalnya, (Windows 64-bit Installer: [Link Download](https://nodejs.org/dist/v26.13.1/node-v26.13.1-x64.msi)) 
2. Download git (kalau belum) install nya sama next next aja, download [Link](https://github.com/git-for-windows/git/releases/download/v2.56.1.windows.1/Git-2.56.1-64-bit.exe)
3. Buka folder tempat kalian mau naruh project laravelnya trus klik kanan di tempat kosong, klik open in terminal
4. Di terminal ketik "git clone https://github.com/MadeAriya/gianyar-planner"
5. Setelah selesai ketik di terminal "cd gianyar-planner" ini buat masuk ke folder projeknya
6. Setelah itu copy file .env yang aku kasih di wa taruh di folder gianyar-planner
7. Setelah itu ketik di terminal yang sama "composer install", tunggu sampai selesai
8. Setelah itu ketik di terminal yang sama "php artisan key:generate", tunggu sampai selesai
9. Setelah itu buka terminal yang sama lagi ketik "php artisan migrate", tunggu sampai selesai
10. Setelah itu masih di terminal yang sama ketik "npm install", tunggu sampai selesai
11. Setelah itu masih di terminal yang sama ketik "npm run build", tunggu sampai selesai
12. Setelah itu masih di terminal yang sama ketik "php artisan serve" ini buat ngerun projectnya, kalau semua step diatas gak ada yang error harusnya bakal muncul link yang bisa diakses di browser
