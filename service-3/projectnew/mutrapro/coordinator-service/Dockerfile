FROM node:20-alpine

WORKDIR /usr/src/app

# copy file package.json + package-lock.json
COPY package*.json ./

# Cài tất cả dependencies (bao gồm devDependencies, để có @nestjs/cli)
RUN npm install

# Copy toàn bộ source code vào container
COPY . .

# Build NestJS (dùng lệnh "nest build" từ devDependencies)
RUN npm run build

# App bên trong container sẽ lắng nghe port 3000
EXPOSE 3000

# Lệnh chạy app
CMD ["node", "dist/main.js"]
