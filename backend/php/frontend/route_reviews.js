// Route Reviews for Velora
// User ratings and feedback (using fake data for demo)

class RouteReviews {
  constructor() {
    this.reviews = this.generateFakeReviews();
  }
  
  generateFakeReviews() {
    const reviewTemplates = [
      { rating: 5, text: "Excellent route! Bus was clean and on time. Highly recommended!", author: "Priya K.", time: "2 days ago" },
      { rating: 4, text: "Good experience overall. Slight delay but comfortable journey.", author: "Rahul M.", time: "1 week ago" },
      { rating: 5, text: "Best route for this destination. Driver was very professional.", author: "Anjali S.", time: "3 days ago" },
      { rating: 3, text: "Decent route but bus was a bit crowded during peak hours.", author: "Suresh R.", time: "5 days ago" },
      { rating: 5, text: "Smooth ride, great views, and arrived 10 minutes early!", author: "Maya P.", time: "1 day ago" },
      { rating: 4, text: "Comfortable seats and AC worked well. Would travel again.", author: "Karthik V.", time: "4 days ago" },
      { rating: 5, text: "Perfect timing and very clean. Staff was helpful too.", author: "Divya N.", time: "6 days ago" },
      { rating: 4, text: "Good route but could use better stops for refreshments.", author: "Arun B.", time: "1 week ago" },
      { rating: 5, text: "Loved this route! Scenic and comfortable. 10/10!", author: "Lakshmi T.", time: "2 days ago" },
      { rating: 3, text: "Average experience. Bus was old but service was okay.", author: "Vijay K.", time: "3 weeks ago" }
    ];
    
    return reviewTemplates;
  }
  
  getReviewsForRoute(mode) {
    // Return random 3-5 reviews
    const count = 3 + Math.floor(Math.random() * 3);
    const shuffled = [...this.reviews].sort(() => 0.5 - Math.random());
    return shuffled.slice(0, count);
  }
  
  getAverageRating(reviews) {
    if (!reviews || reviews.length === 0) return 0;
    const sum = reviews.reduce((acc, r) => acc + r.rating, 0);
    return (sum / reviews.length).toFixed(1);
  }
  
  renderStars(rating) {
    const fullStars = Math.floor(rating);
    const halfStar = rating % 1 >= 0.5;
    const emptyStars = 5 - fullStars - (halfStar ? 1 : 0);
    
    return '⭐'.repeat(fullStars) + 
           (halfStar ? '✨' : '') + 
           '☆'.repeat(emptyStars);
  }
  
  renderReviewCard(review) {
    return `
      <div class="review-card" style="
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 14px;
        margin-bottom: 10px;
        transition: all 0.2s;
      " onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)'" onmouseout="this.style.boxShadow='none'">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
          <div>
            <div style="color: #f59e0b; font-size: 14px; margin-bottom: 4px;">
              ${this.renderStars(review.rating)}
            </div>
            <div style="font-weight: 600; color: #0f172a; font-size: 13px;">${review.author}</div>
          </div>
          <div style="font-size: 11px; color: #94a3b8;">${review.time}</div>
        </div>
        <div style="font-size: 13px; color: #475569; line-height: 1.5;">
          "${review.text}"
        </div>
      </div>
    `;
  }
  
  [REDACTED](mode) {
    const reviews = this.getReviewsForRoute(mode);
    const avgRating = this.getAverageRating(reviews);
    
    return `
      <div class="reviews-section" style="background: white; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 12px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
          <h4 style="margin: 0; font-size: 15px; color: #0f172a; display: flex; align-items: center; gap: 8px;">
            <span>⭐</span> User Reviews
          </h4>
          <div style="text-align: right;">
            <div style="font-size: 20px; font-weight: 700; color: #f59e0b;">${avgRating}</div>
            <div style="font-size: 11px; color: #64748b;">${reviews.length} reviews</div>
          </div>
        </div>
        
        <div style="max-height: 300px; overflow-y: auto;">
          ${reviews.map(r => this.renderReviewCard(r)).join('')}
        </div>
        
        <button style="
          width: 100%;
          padding: 10px;
          margin-top: 12px;
          border: 1px solid #e2e8f0;
          border-radius: 8px;
          background: white;
          color: #6366f1;
          font-weight: 600;
          font-size: 13px;
          cursor: pointer;
          transition: all 0.2s;
        " onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'" onclick="alert('Review feature coming soon! This is demo data.')">
          Write a Review
        </button>
      </div>
    `;
  }
  
  renderCompactRating(mode) {
    const reviews = this.getReviewsForRoute(mode);
    const avgRating = this.getAverageRating(reviews);
    
    return `
      <div style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: #fef3c7; border-radius: 999px; font-size: 12px;">
        <span style="color: #f59e0b;">⭐ ${avgRating}</span>
        <span style="color: #92400e;">(${reviews.length})</span>
      </div>
    `;
  }
}

// Create global instance
window.RouteReviews = new RouteReviews();

// Service loaded

/* v-sync seq: 14 */