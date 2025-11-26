# 1. Image Node.js
FROM node:20-alpine

# 2. Thư mục làm việc
WORKDIR /usr/src/app

# 3. Copy file package*
COPY package*.json ./

# 4. Cài dependency với --legacy-peer-deps để xử lý conflict với NestJS 11
RUN npm install --legacy-peer-deps 
# 5. Copy source code
COPY . .

# 6. Build project
RUN npm run build

# 7. Khai báo port
EXPOSE 3001

# 8. Lệnh chạy app
CMD ["node", "dist/main.js"]
