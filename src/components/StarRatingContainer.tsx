import { motion } from 'motion/react';
import { Star } from 'lucide-react';


const StarRatingContainer = ({
    rating,
    animated = false,
  }: {
    rating: number
    animated?: boolean
}) => {
  return (
    <div className="flex gap-0.5">
      {[1, 2, 3, 4, 5].map((star) => (
        <motion.div
          key={star}
          initial={animated ? { opacity: 0, scale: 0 } : false}
          whileInView={animated ? { opacity: 1, scale: 1 } : undefined}
          viewport={{ once: true }}
          transition={{ delay: star * 0.06, duration: 0.2, ease: 'backOut' as const }}
        >
          <Star
            size={14}
            className={star <= rating ? 'text-[#FFB800] fill-[#FFB800]' : 'text-[#333333]'}
          />
        </motion.div>
      ))}
    </div>
  )
}

export default StarRatingContainer
