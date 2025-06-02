# VIPOS - WordPress POS Plugin

## Mô tả
VIPOS là một plugin Point of Sale (POS) cho WordPress, tích hợp với WooCommerce để cung cấp giải pháp bán hàng tại quầy toàn diện.

## Tính năng chính

### 1. Giao diện POS Fullscreen
- Giao diện toàn màn hình tối ưu cho việc bán hàng
- Header với nút quay về trang admin WordPress
- Layout chia 2 cột: Giỏ hàng (trái) và Danh sách sản phẩm (phải)

### 2. Quản lý Giỏ hàng (Cột trái)
- **Search sản phẩm**: Text box tìm kiếm sản phẩm nhanh
- **Giỏ hàng**: Hiển thị danh sách sản phẩm đã chọn
  - Tên sản phẩm
  - Số lượng (có thể điều chỉnh)
  - Giá đơn vị
  - Tổng tiền từng item
- **Áp dụng giảm giá**: 
  - Giảm giá theo phần trăm
  - Giảm giá theo số tiền cố định
- **Tính thuế**: Tự động tính thuế dựa trên cài đặt WooCommerce
- **Nút thanh toán**: Xử lý thanh toán và tạo đơn hàng

### 3. Danh sách sản phẩm (Cột phải)
- **Search khách hàng**: Text box tìm kiếm thông tin khách hàng
- **Grid sản phẩm**: Hiển thị sản phẩm dạng card
  - Layout 5 cột
  - Hình ảnh sản phẩm
  - Tên sản phẩm
  - Giá bán
  - Trạng thái tồn kho
- **Phân trang**: Điều hướng qua các trang sản phẩm

### 4. Tích hợp WooCommerce
- Đồng bộ dữ liệu sản phẩm từ WooCommerce
- Cập nhật tồn kho real-time
- Tạo đơn hàng WooCommerce sau khi thanh toán
- Quản lý khách hàng WooCommerce

## Cấu trúc Plugin

### Thư mục và Files
```
vipos/
├── vipos.php                 # File chính của plugin
├── README.md                 # Tài liệu
├── includes/                 # Các class PHP chính
│   ├── class-vipos.php       # Class chính
│   ├── class-pos-handler.php # Xử lý logic POS
│   ├── class-product-manager.php # Quản lý sản phẩm
│   ├── class-cart-manager.php    # Quản lý giỏ hàng
│   └── class-order-manager.php   # Quản lý đơn hàng
├── admin/                    # Giao diện admin
│   ├── class-admin.php       # Class admin
│   ├── pos-page.php          # Trang POS
│   └── settings.php          # Cài đặt plugin
├── assets/                   # CSS, JS, Images
│   ├── css/
│   │   ├── pos.css          # CSS cho giao diện POS
│   │   └── admin.css        # CSS cho admin
│   ├── js/
│   │   ├── pos.js           # JavaScript cho POS
│   │   └── admin.js         # JavaScript cho admin
│   └── images/              # Hình ảnh
├── templates/                # Template files
│   └── pos-interface.php     # Template giao diện POS
└── languages/               # File ngôn ngữ
    ├── vipos.pot
    └── vi/
        ├── vipos.po
        └── vipos.mo
```

## Yêu cầu hệ thống

### WordPress
- Phiên bản: 5.0 trở lên
- PHP: 7.4 trở lên

### Plugin dependencies
- **WooCommerce**: 5.0 trở lên (bắt buộc)
- **WordPress REST API**: Enabled

### Trình duyệt hỗ trợ
- Chrome 80+
- Firefox 75+
- Safari 13+
- Edge 80+

## Cài đặt

1. Tải plugin về thư mục `/wp-content/plugins/vipos/`
2. Kích hoạt plugin trong WordPress Admin
3. Đảm bảo WooCommerce đã được cài đặt và kích hoạt
4. Truy cập **VIPOS > Settings** để cấu hình

## Cấu hình

### Cài đặt cơ bản
- **Đơn vị tiền tệ**: Tự động lấy từ WooCommerce
- **Thuế**: Sử dụng cài đặt thuế của WooCommerce
- **Phương thức thanh toán**: Cấu hình các phương thức thanh toán

### Giao diện POS
- **Số sản phẩm hiển thị**: Mặc định 20 sản phẩm/trang
- **Danh mục sản phẩm**: Chọn danh mục hiển thị trong POS
- **Sắp xếp sản phẩm**: Theo tên, giá, ngày tạo

### Quyền truy cập
- **Roles**: Thiết lập role nào có thể truy cập POS
- **Capabilities**: Quản lý quyền chi tiết

## API Endpoints

### Products
- `GET /wp-json/vipos/v1/products` - Lấy danh sách sản phẩm
- `GET /wp-json/vipos/v1/products/search` - Tìm kiếm sản phẩm

### Cart
- `POST /wp-json/vipos/v1/cart/add` - Thêm sản phẩm vào giỏ
- `PUT /wp-json/vipos/v1/cart/update` - Cập nhật giỏ hàng
- `DELETE /wp-json/vipos/v1/cart/remove` - Xóa sản phẩm khỏi giỏ

### Orders
- `POST /wp-json/vipos/v1/orders` - Tạo đơn hàng mới
- `GET /wp-json/vipos/v1/orders` - Lấy danh sách đơn hàng

### Customers
- `GET /wp-json/vipos/v1/customers/search` - Tìm kiếm khách hàng

## Hooks và Filters

### Actions
- `vipos_before_checkout` - Trước khi thanh toán
- `vipos_after_checkout` - Sau khi thanh toán thành công
- `vipos_product_added_to_cart` - Khi thêm sản phẩm vào giỏ

### Filters
- `vipos_products_query` - Lọc query sản phẩm
- `vipos_cart_total` - Lọc tổng tiền giỏ hàng
- `vipos_tax_calculation` - Lọc cách tính thuế

## Bảo mật

- **Nonce verification**: Tất cả AJAX requests
- **Capability checks**: Kiểm tra quyền truy cập
- **Data sanitization**: Làm sạch dữ liệu đầu vào
- **SQL injection prevention**: Sử dụng prepared statements

## Performance

- **Caching**: Cache danh sách sản phẩm
- **Lazy loading**: Tải sản phẩm theo trang
- **Minified assets**: CSS và JS được minify
- **Database optimization**: Query optimization

## Troubleshooting

### Lỗi thường gặp

1. **Không hiển thị sản phẩm**
   - Kiểm tra WooCommerce đã kích hoạt
   - Kiểm tra có sản phẩm nào đã publish

2. **Lỗi Ajax**
   - Kiểm tra REST API hoạt động
   - Kiểm tra quyền truy cập

3. **Giao diện bị vỡ**
   - Kiểm tra conflict với theme/plugin khác
   - Tắt cache plugin

## Changelog

### Version 1.0.0
- Phiên bản đầu tiên
- Giao diện POS fullscreen
- Tích hợp WooCommerce cơ bản
- Quản lý giỏ hàng và thanh toán

## Hỗ trợ

- **Email**: support@vipos.vn
- **Documentation**: [vipos.vn/docs](https://vipos.vn/docs)
- **GitHub**: [github.com/ilmsadmin/vipos_wordpress](https://github.com/ilmsadmin/vipos_wordpress)

## License

GPL v2 or later
