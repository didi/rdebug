package replaying

import (
	"encoding/base64"
	"encoding/json"
	"fmt"
	"io/ioutil"
	"sort"
	"strconv"
	"strings"
	"testing"
	"time"

	"github.com/didi/rdebug/koala/recording"
	"github.com/didi/rdebug/koala/replaying/lexer"
	"github.com/stretchr/testify/require"
)

type IdxScore struct {
	Index int
	Score float64
}

type TermWeight struct {
	Term   string
	Weight float64
}

var escapeChars = [256]byte{
	'a': '\a', 'b': '\b', 'f': '\f', 'n': '\n', 'r': '\r', 't': '\t', 'v': '\v', '\\': '\\', '"': '"', '\'': '\'', '?': '?',
}

func Test_match_best_score(t *testing.T) {
	should := require.New(t)
	talk1 := &recording.CallOutbound{Request: []byte{1, 2, 3, 4, 5, 6, 7, 8, 1, 2, 3, 4, 5, 6, 7, 8}}
	talk2 := &recording.CallOutbound{Request: []byte{1, 2, 3, 4, 5, 6, 7, 8, 1, 2, 3, 4, 5, 6, 7, 7}}
	replayingSession := ReplayingSession{
		CallOutbounds: []*recording.CallOutbound{talk1, talk2},
	}
	_, _, matched := Matcher.Match(NewConnMatchContext(nil, -1), []byte{1, 2, 3, 4, 5, 6, 7, 8, 1, 2, 3, 4, 5, 6, 7, 8}, &replayingSession)
	should.Equal(talk1, matched)
}

func Test_match_not_matched(t *testing.T) {
	should := require.New(t)
	talk1 := &recording.CallOutbound{Request: []byte{1, 2, 3, 4, 5, 6, 7, 8, 1, 2, 3, 4, 5, 6, 7, 8}}
	talk2 := &recording.CallOutbound{Request: []byte{1, 2, 3, 4, 5, 6, 7, 8, 1, 2, 3, 4, 5, 6, 7, 8}}
	talk3 := &recording.CallOutbound{Request: []byte{1, 2, 3, 4, 5, 6, 7, 8, 1, 2, 3, 4, 5, 6, 7, 8}}
	replayingSession := ReplayingSession{
		CallOutbounds: []*recording.CallOutbound{talk1, talk2, talk3},
	}

	connCtx := NewConnMatchContext(nil, -1)
	index, _, _ := Matcher.Match(connCtx, []byte{1, 2, 3, 4, 5, 6, 7, 8, 1, 2, 3, 4, 5, 6, 7, 8}, &replayingSession)
	should.Equal(0, index)
	index, _, _ = Matcher.Match(connCtx, []byte{1, 2, 3, 4, 5, 6, 7, 8, 1, 2, 3, 4, 5, 6, 7, 8}, &replayingSession)
	should.Equal(1, index)
}

func Test_bad_case(t *testing.T) {
	should := require.New(t)
	bytes, err := ioutil.ReadFile("/tmp/koala-original-session.json")
	should.Nil(err)
	origSession := NewReplayingSession()
	err = json.Unmarshal(bytes, origSession)
	bytes, err = ioutil.ReadFile("/tmp/koala-replayed-session.json")
	should.Nil(err)
	var replayedSession interface{}
	err = json.Unmarshal(bytes, &replayedSession)
	should.Nil(err)

	fmt.Println(string(origSession.CallOutbounds[1].Request))
	reqStr := get(replayedSession, "Actions", 22, "Request").(string)
	req, _ := base64.StdEncoding.DecodeString(reqStr)
	fmt.Println(string(req))

	connCtx := NewConnMatchContext(nil, -1)
	index, mark, matched := Matcher.Match(connCtx, req, origSession)
	should.NotNil(matched)
	fmt.Println(string(matched.Request))
	fmt.Println(mark)
	should.Equal(1, index)
}

func Test_similarity_by_request(t *testing.T) {

	req := `GET /foundation/coupon/v1/couponinterface/getAvailableCoupons?pid=500&page=1&productid=20&orderid=1 HTTP/1.1
Host: 10.69.28.59:8000
Accept: */*
didi-header-rid: 3
didi-header-spanid: 8
`

	should := require.New(t)
	orig, err := ioutil.ReadFile("/tmp/midi/session/session-1554172892148078842-26542-original.json")
	should.Nil(err)
	replayingSession := NewReplayingSession()
	err = json.Unmarshal(orig, replayingSession)
	should.Nil(err)

	recordOutboundsCount := len(replayingSession.CallOutbounds)
	rawRecordOutbounds := make(map[int]string)
	recordOutboundsToken := make(map[int][]string, recordOutboundsCount)
	recordOutboundsVector := make(map[int]map[string]float64, recordOutboundsCount)
	for _, outbound := range replayingSession.CallOutbounds {
		req := string(outbound.Request)
		rawRecordOutbounds[outbound.ActionIndex] = req
		recordOutboundsToken[outbound.ActionIndex] = lexer.Lex(outbound.Request)
		recordOutboundsVector[outbound.ActionIndex] = lexer.Lex2Vector(outbound.Request)
	}

	maxScore := 0.0
	maxScoreIdx := -1
	var scores []IdxScore
	test := lexer.Lex2Vector([]byte(req))
	for idx, recordOutboundVector := range recordOutboundsVector {
		sim := CosineSimilarity(recordOutboundVector, test)
		if sim > maxScore {
			maxScore = sim
			maxScoreIdx = idx
		}
		scores = append(scores, IdxScore{Index: idx, Score: sim})
	}
	sort.Slice(scores, func(i, j int) bool {
		return scores[i].Score > scores[j].Score
	})

	fmt.Printf("MaxScoreIdx: %d, Max score: %f, Second △: %f, Top 3 scores: %+v\n", maxScoreIdx, maxScore,
		100*(scores[0].Score-scores[1].Score), scores[0:3])
}

func Test_similarity_by_replayed_actionid(t *testing.T) {
	should := require.New(t)
	orig, err := ioutil.ReadFile("/tmp/midi/session/session-1554172892148078842-26542-original.json")
	should.Nil(err)
	replayingSession := NewReplayingSession()
	err = json.Unmarshal(orig, replayingSession)
	should.Nil(err)

	recordOutboundsCount := len(replayingSession.CallOutbounds)
	rawRecordOutbounds := make(map[int]string)
	recordOutboundsToken := make(map[int][]string, recordOutboundsCount)
	recordOutboundsVector := make(map[int]map[string]float64, recordOutboundsCount)
	for _, outbound := range replayingSession.CallOutbounds {
		req := string(outbound.Request)
		rawRecordOutbounds[outbound.ActionIndex] = req
		recordOutboundsToken[outbound.ActionIndex] = lexer.Lex(outbound.Request)
		recordOutboundsVector[outbound.ActionIndex] = lexer.Lex2Vector(outbound.Request)
	}

	replayed, err := ioutil.ReadFile("/tmp/midi/session/session-1554172892148078842-26542-replayed.json")
	should.Nil(err)
	var replayedSession interface{}
	err = json.Unmarshal(replayed, &replayedSession)
	should.Nil(err)

	// here
	targetActionId := "1555417324965080000"

	calleds := get(replayedSession, "Actions").([]interface{})
	var targetReq string
	for _, replayedCall := range calleds {
		called := replayedCall.(map[string]interface{})
		actionId := called["ActionId"].(string)
		actionType := called["ActionType"]
		if actionType != "CallOutbound" || actionId != targetActionId {
			continue
		}
		targetReq = unescape(called["Request"].(string))
	}

	maxScore := 0.0
	maxScoreIdx := -1
	var scores []IdxScore
	test := lexer.Lex2Vector([]byte(targetReq))
	for idx, recordOutboundVector := range recordOutboundsVector {
		sim := CosineSimilarity(recordOutboundVector, test)
		if sim > maxScore {
			maxScore = sim
			maxScoreIdx = idx
		}
		scores = append(scores, IdxScore{Index: idx, Score: sim})
	}
	sort.Slice(scores, func(i, j int) bool {
		return scores[i].Score > scores[j].Score
	})

	fmt.Printf("MaxScoreIdx: %d, Max score: %f, Second △: %f, Top 3 scores: %+v\n", maxScoreIdx, maxScore,
		100*(scores[0].Score-scores[1].Score), scores[0:3])
}

func Test_cos_similarity(t *testing.T) {
	should := require.New(t)

	orig, err := ioutil.ReadFile("/tmp/midi/session/session-1554172892148078842-26542-original.json")
	should.Nil(err)
	replayingSession := NewReplayingSession()
	err = json.Unmarshal(orig, replayingSession)
	should.Nil(err)

	replayed, err := ioutil.ReadFile("/tmp/midi/session/session-1554172892148078842-26542-replayed.json")
	should.Nil(err)
	var replayedSession interface{}
	err = json.Unmarshal(replayed, &replayedSession)
	should.Nil(err)

	begin := time.Now()
	recordOutboundsCount := len(replayingSession.CallOutbounds)
	rawRecordOutbounds := make(map[int]string)
	recordOutboundsToken := make(map[int][]string, recordOutboundsCount)
	recordOutboundsVector := make(map[int]map[string]float64, recordOutboundsCount)
	for _, outbound := range replayingSession.CallOutbounds {
		req := string(outbound.Request)
		rawRecordOutbounds[outbound.ActionIndex] = req
		recordOutboundsToken[outbound.ActionIndex] = lexer.Lex(outbound.Request)
		recordOutboundsVector[outbound.ActionIndex] = lexer.Lex2Vector(outbound.Request)
	}
	fmt.Printf("Online record CallOutbound count: %d\n", recordOutboundsCount)

	calledCount := 0
	cosMatchedCount := 0
	var scores [][]IdxScore
	calleds := get(replayedSession, "Actions").([]interface{})
	for _, replayedCall := range calleds {
		called := replayedCall.(map[string]interface{})
		actionId := called["ActionId"].(string)
		actionType := called["ActionType"]
		if actionType != "CallOutbound" {
			continue
		}
		calledCount += 1
		request := unescape(called["Request"].(string))
		matchedIdx := int(called["MatchedActionIndex"].(float64))

		maxScore := 0.0
		maxScoreIdx := -1
		var score []IdxScore
		test := lexer.Lex2Vector([]byte(request))
		for idx, recordOutboundVector := range recordOutboundsVector {
			sim := CosineSimilarity(recordOutboundVector, test)
			if sim > maxScore {
				maxScore = sim
				maxScoreIdx = idx
			}
			score = append(score, IdxScore{Index: idx, Score: sim})
		}
		scores = append(scores, score)
		sort.Slice(score, func(i, j int) bool {
			return score[i].Score > score[j].Score
		})

		fmt.Printf("Replayed ActionId: %s, Max score: %f, Second △: %f, Top 3 scores: %+v\n", actionId, maxScore, 100*(score[0].Score-score[1].Score), score[0:3])

		// for debug, see top 3 matched request
		//if actionId == "1554955091747692000" {
		//	fmt.Println("-----------Req--------------")
		//	fmt.Println(called["Request"].(string))
		//	fmt.Println("------------0-------------")
		//	fmt.Println(rawRecordOutbounds[score[0].Index])
		//	fmt.Println("------------1-------------")
		//	fmt.Println(rawRecordOutbounds[score[1].Index])
		//	fmt.Println("------------2-------------")
		//	fmt.Println(rawRecordOutbounds[score[2].Index])
		//	fmt.Println("------------3-------------")
		//	fmt.Println(rawRecordOutbounds[score[3].Index])
		//}

		if maxScoreIdx == matchedIdx {
			cosMatchedCount += 1
		} else {
			if maxScoreIdx != -1 && request == rawRecordOutbounds[maxScoreIdx] {
				// 存在两个一模一样的请求
				cosMatchedCount += 1
			} else {
				//// not match
				//fmt.Println("Not Matched Req:\n", request)
				//fmt.Println("Online Idx: \n", matchedIdx)
				//fmt.Println("Test Idx: \n", maxScoreIdx)
				//fmt.Println("Matched Req:\n", recordOutbounds[maxScoreIdx])
				//
				//online := f.Cal(recordOutbounds[matchedIdx])
				//test := f.Cal(request)
				//sim := matcher.CosineSimilarity(online, test)
				//fmt.Println("Matched Sim:\n", sim)
				//
				//online = f.Cal(recordOutbounds[maxScoreIdx])
				//test = f.Cal(request)
				//sim = matcher.CosineSimilarity(online, test)
				//fmt.Println("Chunk Matched Sim:\n", sim)
			}
		}
	}
	fmt.Printf("called %d, sim match count %d, spend %s\n", calledCount, cosMatchedCount, time.Since(begin))

	should.Equal(calledCount, cosMatchedCount)
}

func Test_bad_case3(t *testing.T) {
	should := require.New(t)
	bytes, err := ioutil.ReadFile("/tmp/midi/session/koala-session-1537185583764907631-32020-original.json")
	should.Nil(err)
	origSession := NewReplayingSession()
	err = json.Unmarshal(bytes, origSession)
	bytes, err = ioutil.ReadFile("/tmp/midi/session/koala-session-1537185583764907631-32020-replayed.json")
	should.Nil(err)
	var replayedSession interface{}
	err = json.Unmarshal(bytes, &replayedSession)
	should.Nil(err)

	req := get(replayedSession, "Actions", 263, "Request")
	reqBytes := unescape(req.(string))
	fmt.Println(reqBytes)

	index, mark, matched := Matcher.Match(NewConnMatchContext(nil, 22), []byte(reqBytes), origSession)
	should.NotNil(matched)
	fmt.Println(string(matched.Request))
	fmt.Println(mark)
	should.Equal(1, index)
}

func Test_chunk_match(t *testing.T) {
	should := require.New(t)
	bytes, err := ioutil.ReadFile("/tmp/midi/session//koala-session-1543454668151570983-12038-original.json")
	should.Nil(err)
	origSession := NewReplayingSession()
	err = json.Unmarshal(bytes, origSession)
	bytes, err = ioutil.ReadFile("/tmp/midi/session//koala-session-1543454668151570983-12038-replayed.json")
	should.Nil(err)
	var replayedSession interface{}
	err = json.Unmarshal(bytes, &replayedSession)
	should.Nil(err)

	req := get(replayedSession, "Actions", 20, "Request")
	fmt.Printf("req type:%T\n", req)
	fmt.Println(req)
	reqBytes := unescape(req.(string))
	fmt.Println(reqBytes)

	connCtx := NewConnMatchContext(nil, 0)
	index, mark, matched := Matcher.Match(connCtx, []byte(reqBytes), origSession)
	should.NotNil(matched)
	fmt.Println(string(matched.Request))
	fmt.Println(mark)
	should.Equal(2, index)
}

//func Test_min_hash(t *testing.T) {
//	should := require.New(t)
//	bytes, err := ioutil.ReadFile("/tmp/midi/session/koala-session-1543454668151570983-12038-original.json")
//	should.Nil(err)
//	origSession := NewReplayingSession()
//	err = json.Unmarshal(bytes, origSession)
//	bytes, err = ioutil.ReadFile("/tmp/midi/session/koala-session-1543454668151570983-12038-replayed.json")
//	should.Nil(err)
//	var replayedSession interface{}
//	err = json.Unmarshal(bytes, &replayedSession)
//	should.Nil(err)
//
//	req := get(replayedSession, "Actions", 26, "Request")
//	fmt.Printf("req type:%T\n", req)
//	fmt.Println(req)
//	reqBytes := unescape(req.(string))
//	fmt.Println(reqBytes)
//
//	index, mark, matched := origSession.MatchOutboundTalk(nil, -1, []byte(reqBytes))
//	should.NotNil(matched)
//	fmt.Println(string(matched.Request))
//	fmt.Println(mark)
//	should.Equal(2, index)
//
//	index, mark, matched = origSession.minHashMatch(nil, -1, []byte(reqBytes))
//	should.NotNil(matched)
//	fmt.Println(string(matched.Request))
//	fmt.Println(mark)
//	should.Equal(2, index)
//}

func get(obj interface{}, keys ...interface{}) interface{} {
	for _, key := range keys {
		switch typedKey := key.(type) {
		case int:
			obj = obj.([]interface{})[typedKey]
		case string:
			obj = obj.(map[string]interface{})[typedKey]
		default:
			panic("unsupported key type")
		}
	}
	return obj
}

func unescape(s string) string {
	// NB: Sadly, we can't use strconv.Unquote because protoc will escape both
	// single and double quotes, but strconv.Unquote only allows one or the
	// other (based on actual surrounding quotes of its input argument).
	var out []byte
	for len(s) > 0 {
		// regular character, or too short to be valid escape
		if s[0] != '\\' || len(s) < 2 {
			out = append(out, s[0])
			s = s[1:]
		} else if c := escapeChars[s[1]]; c != 0 {
			// escape sequence
			out = append(out, c)
			s = s[2:]
		} else if s[1] == 'x' || s[1] == 'X' {
			// hex escape, e.g. "\x80
			if len(s) < 4 {
				// too short to be valid
				out = append(out, s[:2]...)
				s = s[2:]
				continue
			}
			v, err := strconv.ParseUint(s[2:4], 16, 8)
			if err != nil {
				out = append(out, s[:4]...)
			} else {
				out = append(out, byte(v))
			}
			s = s[4:]
		} else if '0' <= s[1] && s[1] <= '7' {
			// octal escape, can vary from 1 to 3 octal digits; e.g., "\0" "\40" or "\164"
			// so consume up to 2 more bytes or up to end-of-string
			n := len(s[1:]) - len(strings.TrimLeft(s[1:], "01234567"))
			if n > 3 {
				n = 3
			}
			v, err := strconv.ParseUint(s[1:1+n], 8, 8)
			if err != nil {
				out = append(out, s[:1+n]...)
			} else {
				out = append(out, byte(v))
			}
			s = s[1+n:]
		} else {
			// bad escape, just propagate the slash as-is
			out = append(out, s[0])
			s = s[1:]
		}
	}
	return string(out)
}

func strSlice2MapWithoutWeight(str []string) map[string]float64 {
	ret := make(map[string]float64, len(str))
	for _, v := range str {
		ret[v] = 1
	}
	return ret
}

func resizeVector(vector map[string]float64, size int) map[string]float64 {
	var list []TermWeight
	for k, v := range vector {
		list = append(list, TermWeight{Term: k, Weight: v})
	}
	sort.Slice(list, func(i, j int) bool {
		return list[i].Weight > list[j].Weight
	})

	i := 0
	resize := make(map[string]float64)
	for _, v := range list {
		i++
		if i > size {
			break
		}
		resize[v.Term] = v.Weight
	}

	return resize
}

func strSlice2Vector(str []string) map[string]float64 {
	ret := make(map[string]float64, len(str))
	for _, v := range str {
		if _, ok := ret[v]; ok {
			ret[v] += float64(len(v))
		} else {
			ret[v] = float64(len(v))
		}
	}
	return ret
}