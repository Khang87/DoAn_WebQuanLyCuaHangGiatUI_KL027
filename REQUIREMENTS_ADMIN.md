## QUY TẮC TỰ ĐỘNG NÓI TIẾP & SỬ DỤNG MODEL (AUTO-CONTINUE & FREE MODEL RULE)
- Nếu dung lượng Context Window tiệm cận giới hạn hoặc sau khi thực hiện Compaction, hãy TỰ ĐỘNG tiếp tục công việc ngay ở bước tiếp theo mà không dừng lại chờ câu lệnh "Tiếp tục" từ tôi.
- Nếu phản hồi bị cắt ngang do chạm trần Output Token, hãy tự hoàn thiện phần code còn dở dang ngay trong lượt phản hồi kế tiếp.
- Nếu gặp lỗi API do Model hết lượt dùng free (Rate limit / Quota exceeded / Payment required), hãy chủ động thông báo tên Model miễn phí thay thế (như `google/gemini-2.5-flash` hoặc `qwen/qwen-2.5-coder-32b`hoặc các model free tương tự) và nhắc tôi cập nhật lại Model ID.
### NHIỆM VỤ: BỔ SUNG QUY TẮC BẮT BUỘC VỀ ĐƯỜNG DẪN DỰ ÁN VÀO TẬP RULE CỦA KILO (.KILORULES)

Chào Kilo. Hãy cập nhật ngay Quy tắc hệ thống (System Rules / Workspace Context) với thông tin đường dẫn tuyệt đối bắt buộc dưới đây.

---

#### 1. QUY TẮC RÀNG BUỘC ĐƯỜNG DẪN DỰ ÁN (ABSOLUTE PROJECT PATH RULE)

- **ĐƯỜNG DẪN GỐC BẮT BUỘC (ROOT ABSOLUTE PATH):**
  ```text
  D:\laragon\www\Web_Laravel_QuanLyGiatUi\Laravel_Web_QuanLyCuaHangGiatUi