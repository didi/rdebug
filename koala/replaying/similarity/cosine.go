package similarity

import (
	"math"
)

func Cosine(a, b map[string]float64) (sim float64) {
	prod, aSquareSum, bSquareSum := 0.0, 0.0, 0.0

	for aTerm, aWeight := range a {
		if bWeight, ok := b[aTerm]; ok {
			prod += aWeight * bWeight
		}
		aSquareSum += aWeight * aWeight
	}
	for _, bWeight := range b {
		bSquareSum += bWeight * bWeight
	}

	if aSquareSum == 0 || bSquareSum == 0 {
		return 0
	}

	return prod / (math.Sqrt(aSquareSum) * math.Sqrt(bSquareSum))
}
